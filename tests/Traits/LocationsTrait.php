<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Traits;

use AlexSkrypnyk\File\File;
use Symfony\Component\Finder\Finder;

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
   * Copy files from the source to the destination directory.
   *
   * @param string $src
   *   The source directory to copy files from.
   * @param string $dst
   *   The destination directory.
   * @param array $include
   *   An array of files to include in the copy. If empty, no files will be
   *   copied.
   * @param array $exclude
   *   An array of excluded directories or files.
   * @param callable|null $before
   *   A callback function to modify the source and destination paths before
   *   copying.
   *
   * @return array
   *   An array of created file paths.
   */
  protected static function locationsCopy(string $src, string $dst, array $include = [], array $exclude = [], ?callable $before = NULL): array {
    $created = [];

    // Default exclusions.
    $exclusions = array_merge([
      '.git',
      'node_modules',
      'vendor',
    ], $exclude);

    $src = File::dir($src);

    $finder = new Finder();
    $finder->files()
      ->in($src)
      ->ignoreDotFiles(FALSE)
      ->ignoreVCS(FALSE)
      ->filter(function (\SplFileInfo $file) use ($src, $include): bool {
        if (empty($include)) {
          return TRUE;
        }
        $real_path = $file->getRealPath();
        if (!$real_path) {
          return FALSE;
        }
        $relative_path = str_starts_with($real_path, $src)
          ? str_replace($src . DIRECTORY_SEPARATOR, '', $real_path)
          : $real_path;
        foreach ($include as $path) {
          $path = File::realpath($path);
          if ($path === $real_path || $path === $relative_path) {
            return TRUE;
          }
        }
        return FALSE;
      })
      ->exclude($exclusions);

    foreach ($finder as $file) {
      $src_path = $file->getRealPath();
      $dst_path = $dst . DIRECTORY_SEPARATOR . $file->getRelativePathname();
      if (is_callable($before)) {
        $before($src_path, $dst_path);
      }
      File::copy($src_path, $dst_path);
      $created[] = File::realpath($dst_path);
    }

    return $created;
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
    return static::locationsCopy($basedir ?: File::cwd(), static::$sut, $files, [], function (string &$src, string &$dst) use ($append_rand): void {
      $dst .= ($append_rand ? rand(1000, 9999) : '');
    });
  }

}
