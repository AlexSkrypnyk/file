<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Traits;

use AlexSkrypnyk\File\Exception\PatchException;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\Index;

/**
 * Assertions for testing directory contents.
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait DirectoryAssertionsTrait {

  /**
   * Ignored paths for directory assertion operations.
   *
   * This method can be overridden in test classes to specify subpaths that
   * should be ignored during directory content searches. Paths are merged
   * with any explicitly provided excluded paths in assertion methods.
   *
   * Example usage in test class:
   * ```php
   * public static function ignoredPath(): array {
   *   return ['.git', 'node_modules', 'temp/cache'];
   * }
   * ```
   *
   * @return array
   *   An array of literal subpaths to ignore during directory assertions.
   */
  public static function ignoredPaths(): array {
    return [];
  }

  /**
   * Assert that a directory contains files with a specific string.
   *
   * @param string $directory
   *   The directory to search in.
   * @param string $needle
   *   The string to search for in files.
   * @param array $ignored
   *   An array of paths to exclude from the search.
   * @param string|null $message
   *   Optional custom failure message.
   */
  public function assertDirectoryContainsString(string $directory, string $needle, array $ignored = [], ?string $message = NULL): void {
    $files = File::containsInDir($directory, $needle, array_merge(static::ignoredPaths(), $ignored));

    if (empty($files)) {
      $this->fail($message ?: sprintf('Directory should contain "%s", but it does not.', $needle));
    }
  }

  /**
   * Assert that a directory does not contain files with a specific string.
   *
   * @param string $directory
   *   The directory to search in.
   * @param string $needle
   *   The string to search for in files.
   * @param array $ignored
   *   An array of paths to exclude from the search.
   * @param string|null $message
   *   Optional custom failure message.
   */
  public function assertDirectoryNotContainsString(string $directory, string $needle, array $ignored = [], ?string $message = NULL): void {
    $files = File::containsInDir($directory, $needle, array_merge(static::ignoredPaths(), $ignored));

    if (!empty($files)) {
      $this->fail($message ?: sprintf('Directory should not contain "%s", but it does within files %s.', $needle, implode(', ', $files)));
    }
  }

  /**
   * Assert that a directory contains files with a specific word.
   *
   * This method uses word boundaries to ensure the needle is found as a
   * complete word, not as part of another word.
   *
   * @param string $directory
   *   The directory to search in.
   * @param string $needle
   *   The word to search for in files.
   * @param array $ignored
   *   An array of paths to exclude from the search.
   * @param string|null $message
   *   Optional custom failure message.
   */
  public function assertDirectoryContainsWord(string $directory, string $needle, array $ignored = [], ?string $message = NULL): void {
    $files = File::containsInDir($directory, '/\b' . preg_quote($needle, '/') . '\b/i', array_merge(static::ignoredPaths(), $ignored));

    if (empty($files)) {
      $this->fail($message ?: sprintf('Directory should contain "%s" word, but it does not.', $needle));
    }
  }

  /**
   * Assert that a directory does not contain files with a specific word.
   *
   * This method uses word boundaries to ensure the needle is found as a
   * complete word, not as part of another word.
   *
   * @param string $directory
   *   The directory to search in.
   * @param string $needle
   *   The word to search for in files.
   * @param array $ignored
   *   An array of paths to exclude from the search.
   * @param string|null $message
   *   Optional custom failure message.
   */
  public function assertDirectoryNotContainsWord(string $directory, string $needle, array $ignored = [], ?string $message = NULL): void {
    $files = File::containsInDir($directory, '/\b' . preg_quote($needle, '/') . '\b/i', array_merge(static::ignoredPaths(), $ignored));

    if (!empty($files)) {
      $this->fail($message ?: sprintf('Directory should not contain "%s" word, but it does within files %s.', $needle, implode(', ', $files)));
    }
  }

  /**
   * Assert that two directories have identical structure and content.
   *
   * @param string $dir1
   *   First directory path to compare.
   * @param string $dir2
   *   Second directory path to compare.
   * @param string|null $message
   *   Optional custom failure message.
   * @param callable|null $match_content
   *   Optional callback to process file content before comparison.
   * @param bool $show_diff
   *   Whether to include diff output in failure messages.
   */
  public function assertDirectoryEqualsDirectory(string $dir1, string $dir2, ?string $message = NULL, ?callable $match_content = NULL, bool $show_diff = TRUE): void {
    $text = File::compare($dir1, $dir2, NULL, $match_content)->render(['show_diff' => $show_diff]);
    if (!empty($text)) {
      $this->fail($message ? $message . PHP_EOL . $text : $text);
    }
    $this->addToAssertionCount(1);
  }

  /**
   * Assert that a directory is equal to the patched baseline (baseline + diff).
   *
   * This method applies patch files to a baseline directory and then compares
   * the resulting directory with an actual directory to verify they match.
   *
   * @param string $actual
   *   Actual directory path to compare.
   * @param string $baseline
   *   Baseline directory path.
   * @param string $diffs
   *   Directory containing diff/patch files to apply to the baseline.
   * @param string|null $expected
   *   Optional path where to create the expected directory. If not provided,
   *   a '.expected' directory will be created next to the baseline.
   * @param string|null $message
   *   Optional custom failure message.
   */
  public function assertDirectoryEqualsPatchedBaseline(string $actual, string $baseline, string $diffs, ?string $expected = NULL, ?string $message = NULL): void {
    if (!is_dir($baseline)) {
      $this->fail($message ?: sprintf('The baseline directory does not exist: %s', $baseline));
    }

    // We use the .expected dir to easily assess the combined expected fixture.
    $expected = $expected ?: File::realpath($baseline . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.expected');
    File::rmdir($expected);

    try {
      File::patch($baseline, $diffs, $expected);
    }
    catch (PatchException $patch_exception) {
      $this->fail($message ?: sprintf('Failed to apply patch: %s', $patch_exception->getMessage()));
    }

    // Do not override .ignorecontent file from the baseline directory.
    if (file_exists($baseline . DIRECTORY_SEPARATOR . Index::IGNORECONTENT)) {
      File::copy($baseline . DIRECTORY_SEPARATOR . Index::IGNORECONTENT, $expected . DIRECTORY_SEPARATOR . Index::IGNORECONTENT);
    }

    $this->assertDirectoryEqualsDirectory($expected, $actual, $message);
  }

}
