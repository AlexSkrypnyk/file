<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Traits;

use AlexSkrypnyk\File\File;

/**
 * Trait Locations.
 *
 * Helpers to work with Locations during tests.
 *
 * Paths are static to allow for easy access from static methods such as
 * data providers.
 */
trait LocationsTrait {

  /**
   * Path to the root directory of this project.
   */
  protected static string $root;

  /**
   * Path to the fixtures directory from the root of this project.
   */
  protected static ?string $fixtures = NULL;

  /**
   * Main workspace directory where the rest of the directories located.
   *
   * The "workspace" in this context is a place to store assets produced by a
   * single test run.
   */
  protected static string $workspace;

  /**
   * Directory used as a source in the operations.
   *
   * Could be a copy of the current repository with custom adjustments or a
   * fixture repository.
   */
  protected static string $repo;

  /**
   * Directory where the test will run.
   */
  protected static string $sut;

  /**
   * Directory where some temp files can be stored.
   */
  protected static string $tmp;

  /**
   * Path to the fixtures directory from the repository root.
   *
   * This method should be overridden in the child class to provide a custom
   * fixtures directory path.
   *
   * @return string
   *   The fixtures directory path relative to the repository root.
   */
  protected static function locationsFixturesDir(): string {
    return 'tests/Fixtures';
  }

  /**
   * Initialize the locations.
   *
   * @param string|null $cwd
   *   The current working directory to use. If NULL, the current working
   *   directory will be used.
   * @param \Closure|null $after
   *   Closure to run after initialization. Closure will be bound to the test
   *   class where this trait is used.
   */
  protected function locationsInit(?string $cwd = NULL, ?callable $after = NULL): void {
    static::$root = File::dir($cwd ?? (string) getcwd());
    static::$workspace = File::mkdir(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'workspace-' . microtime(TRUE));
    static::$repo = File::mkdir(static::$workspace . DIRECTORY_SEPARATOR . 'repo');
    static::$sut = File::mkdir(static::$workspace . DIRECTORY_SEPARATOR . 'sut');
    static::$tmp = File::mkdir(static::$workspace . DIRECTORY_SEPARATOR . 'tmp');

    $fixtures_dir = static::$root . DIRECTORY_SEPARATOR . static::locationsFixturesDir();
    if (is_dir($fixtures_dir)) {
      static::$fixtures = File::dir($fixtures_dir);
    }

    if ($after !== NULL && $after instanceof \Closure) {
      \Closure::bind($after, $this, self::class)();
    }
  }

  /**
   * Tear down the locations.
   *
   * Will be skipped if the DEBUG environment variable is set.
   */
  protected function locationsTearDown(): void {
    if (!getenv('DEBUG')) {
      File::remove(static::$workspace);
    }
  }

  /**
   * Get the fixtures' directory based on the custom name and a test name.
   *
   * If the test uses a data provider with named data sets, the name of the
   * data set converted to a snake case will be appended to the fixture
   * directory name.
   *
   * @param string|null $name
   *   The name of the fixture directory. If not provided, the name will be
   *   generated based on the test name as a snake_case string.
   *
   * @return string
   *   The fixtures directory path.
   */
  protected function locationsFixtureDir(?string $name = NULL): string {
    $fixtures_dir = static::$root . DIRECTORY_SEPARATOR . static::locationsFixturesDir();
    if (!is_dir($fixtures_dir)) {
      throw new \RuntimeException(sprintf('Fixtures directory "%s" does not exist.', $fixtures_dir));
    }

    $path = File::dir($fixtures_dir);

    // Set the fixtures directory based on the passed name.
    if ($name) {
      $path .= DIRECTORY_SEPARATOR . $name;
    }
    else {
      // Set the fixtures directory based on the test name.
      $fixture_dir = $this->name();
      $fixture_dir = str_contains($fixture_dir, '::') ? explode('::', $fixture_dir)[1] : $fixture_dir;
      $fixture_dir = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $fixture_dir));
      $fixture_dir = str_replace('test_', '', $fixture_dir);
      $path .= DIRECTORY_SEPARATOR . $fixture_dir;
    }

    // Further adjust the fixtures directory name if the test uses a
    // data provider with named data sets.
    if (!empty($this->dataName()) && !is_numeric($this->dataName())) {
      $path_suffix = strtolower(str_replace(['-', ' '], '_', (string) preg_replace('/[^a-zA-Z0-9_\- ]/', '', (string) $this->dataName())));
      $path .= DIRECTORY_SEPARATOR . $path_suffix;
    }

    return File::dir($path);
  }

  /**
   * Get the locations' info.
   *
   * @return string
   *   The locations' info.
   */
  protected static function locationsInfo(): string {
    $lines[] = '-- LOCATIONS --';
    $lines[] = 'Root       : ' . static::$root;
    $lines[] = 'Fixtures   : ' . (static::$fixtures ?? 'Not set');
    $lines[] = 'Workspace  : ' . static::$workspace;
    $lines[] = 'Repo       : ' . static::$repo;
    $lines[] = 'SUT        : ' . static::$sut;
    $lines[] = 'Temp       : ' . static::$tmp;
    return implode(PHP_EOL, $lines) . PHP_EOL;
  }

  /**
   * Copy files to the SUT directory.
   *
   * @param array $files
   *   The files to copy.
   * @param string|null $basedir
   *   The base directory to use for the files. If NULL, the base directory
   *   of the first file will be used.
   * @param bool $append_rand
   *   Whether to append a random numeric suffix to the file names.
   *
   * @return array
   *   The list of created file paths.
   */
  protected static function locationsCopyFilesToSut(array $files, ?string $basedir = NULL, bool $append_rand = TRUE): array {
    $created = [];

    foreach ($files as $file) {
      $basedir = $basedir ?? dirname((string) $file);
      $relative = ltrim(str_replace($basedir, '', (string) $file), DIRECTORY_SEPARATOR);
      $dst = static::$sut . DIRECTORY_SEPARATOR . $relative . ($append_rand ? rand(1000, 9999) : '');
      File::copy($file, $dst);
      $created[] = $dst;
    }

    return $created;
  }

}
