<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Traits;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\TestCase;

/**
 * Trait ConsoleTrait.
 *
 * Helpers to work with Console.
 */
trait LocationsTrait {

  /**
   * Path to the root directory of this project.
   */
  protected static string $root;

  /**
   * Path to the fixtures directory from the root of this project.
   */
  protected static string $fixtures;

  /**
   * Main build directory where the rest of the directories located.
   *
   * The "build" in this context is a place to store assets produced by a single
   * test run.
   */
  protected static string $build;

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
   */
  protected static function locationsFixtures(): string {
    return 'tests/Fixtures';
  }

  /**
   * Initialize the locations.
   *
   * @param string $cwd
   *   The current working directory.
   * @param callable|null $cb
   *   Callback to run after initialization.
   * @param \PHPUnit\Framework\TestCase|null $test
   *   The test instance to pass to the callback.
   */
  protected static function locationsInit(string $cwd, ?callable $cb = NULL, ?TestCase $test = NULL): void {
    static::$root = File::dir($cwd, TRUE);
    static::$build = File::dir(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vortex-' . microtime(TRUE), TRUE);
    static::$repo = File::dir(static::$build . '/local_repo', TRUE);
    static::$sut = File::dir(static::$build . '/star_wars', TRUE);
    static::$tmp = File::dir(static::$build . '/tmp', TRUE);

    if ($cb !== NULL && $cb instanceof \Closure) {
      \Closure::bind($cb, $test, self::class)();
    }
  }

  protected static function locationsTearDown(): void {
    File::remove(static::$build);
  }

  protected function locationsFixtureDir(?string $name = NULL): string {
    $path = File::dir(static::$root . DIRECTORY_SEPARATOR . static::locationsFixtures());

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

  protected static function locationsInfo(): string {
    $lines[] = '-- LOCATIONS --';
    $lines[] = 'Root       : ' . static::$root;
    $lines[] = 'Fixtures   : ' . static::$fixtures;
    $lines[] = 'Build      : ' . static::$build;
    $lines[] = 'Local repo : ' . static::$repo;
    $lines[] = 'SUT        : ' . static::$sut;
    $lines[] = 'TMP        : ' . static::$tmp;
    return implode(PHP_EOL, $lines) . PHP_EOL;
  }

  protected static function locationsCopyFilesToSut(array $files, ?string $basedir = NULL, bool $append_rand = TRUE): array {
    $created = [];

    foreach ($files as $file) {
      $basedir = $basedir ?? dirname((string) $file);
      $relative_dst = ltrim(str_replace($basedir, '', (string) $file), '/') . ($append_rand ? rand(1000, 9999) : '');
      $new_name = static::$sut . DIRECTORY_SEPARATOR . $relative_dst;
      File::copy($file, $new_name);
      $created[] = $new_name;
    }

    return $created;
  }

}
