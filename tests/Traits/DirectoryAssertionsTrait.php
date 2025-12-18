<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Traits;

use AlexSkrypnyk\File\File;

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
    $files = File::findContainingInDir($directory, $needle, array_merge(static::ignoredPaths(), $ignored));

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
    $files = File::findContainingInDir($directory, $needle, array_merge(static::ignoredPaths(), $ignored));

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
    $files = File::findContainingInDir($directory, '/\b' . preg_quote($needle, '/') . '\b/i', array_merge(static::ignoredPaths(), $ignored));

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
    $files = File::findContainingInDir($directory, '/\b' . preg_quote($needle, '/') . '\b/i', array_merge(static::ignoredPaths(), $ignored));

    if (!empty($files)) {
      $this->fail($message ?: sprintf('Directory should not contain "%s" word, but it does within files %s.', $needle, implode(', ', $files)));
    }
  }

}
