<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Traits;

use AlexSkrypnyk\File\File;

/**
 * Assertions for testing file contents.
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait FileAssertionsTrait {

  /**
   * Assert that a file contains a specific string.
   *
   * @param string $file
   *   The file to search in.
   * @param string $needle
   *   The string to search for in the file.
   * @param string $message
   *   Optional custom failure message.
   */
  public function assertFileContainsString(string $file, string $needle, string $message = ''): void {
    if (!File::contains($file, $needle)) {
      $this->fail($message ?: sprintf('File "%s" should contain "%s", but it does not.', $file, $needle));
    }
    $this->addToAssertionCount(1);
  }

  /**
   * Assert that a file does not contain a specific string.
   *
   * @param string $file
   *   The file to search in.
   * @param string $needle
   *   The string to search for in the file.
   * @param string $message
   *   Optional custom failure message.
   */
  public function assertFileNotContainsString(string $file, string $needle, string $message = ''): void {
    if (File::contains($file, $needle)) {
      $this->fail($message ?: sprintf('File "%s" should not contain "%s", but it does.', $file, $needle));
    }
    $this->addToAssertionCount(1);
  }

  /**
   * Assert that a file contains a specific word.
   *
   * This method uses word boundaries to ensure the needle is found as a
   * complete word, not as part of another word.
   *
   * @param string $file
   *   The file to search in.
   * @param string $needle
   *   The word to search for in the file.
   * @param string $message
   *   Optional custom failure message.
   */
  public function assertFileContainsWord(string $file, string $needle, string $message = ''): void {
    if (!File::contains($file, '/\b' . preg_quote($needle, '/') . '\b/i')) {
      $this->fail($message ?: sprintf('File "%s" should contain "%s" word, but it does not.', $file, $needle));
    }
    $this->addToAssertionCount(1);
  }

  /**
   * Assert that a file does not contain a specific word.
   *
   * This method uses word boundaries to ensure the needle is found as a
   * complete word, not as part of another word.
   *
   * @param string $file
   *   The file to search in.
   * @param string $needle
   *   The word to search for in the file.
   * @param string $message
   *   Optional custom failure message.
   */
  public function assertFileNotContainsWord(string $file, string $needle, string $message = ''): void {
    if (File::contains($file, '/\b' . preg_quote($needle, '/') . '\b/i')) {
      $this->fail($message ?: sprintf('File "%s" should not contain "%s" word, but it does.', $file, $needle));
    }
    $this->addToAssertionCount(1);
  }

  /**
   * Assert that a file equals another file in contents.
   *
   * @param string $expected
   *   Expected file path.
   * @param string $actual
   *   Actual file path.
   * @param string $message
   *   Optional custom failure message.
   */
  public function assertFileEqualsFile(string $expected, string $actual, string $message = ''): void {
    // Check that both files exist.
    if (!file_exists($expected)) {
      $this->fail($message ?: sprintf('Expected file "%s" does not exist.', $expected));
    }
    if (!file_exists($actual)) {
      $this->fail($message ?: sprintf('Actual file "%s" does not exist.', $actual));
    }

    // Check file contents.
    $this->assertFileEquals($expected, $actual, $message ?: sprintf('File contents of "%s" and "%s" do not match.', $expected, $actual));

    $this->addToAssertionCount(1);
  }

  /**
   * Assert that a file does not equal another file in contents.
   *
   * @param string $expected
   *   Expected file path.
   * @param string $actual
   *   Actual file path.
   * @param string $message
   *   Optional custom failure message.
   */
  public function assertFileNotEqualsFile(string $expected, string $actual, string $message = ''): void {
    // Check that both files exist.
    if (!file_exists($expected)) {
      $this->fail($message ?: sprintf('Expected file "%s" does not exist.', $expected));
    }
    if (!file_exists($actual)) {
      $this->fail($message ?: sprintf('Actual file "%s" does not exist.', $actual));
    }

    // Check if the contents are different.
    $expected_contents = file_get_contents($expected);
    $actual_contents = file_get_contents($actual);

    if ($expected_contents === $actual_contents) {
      $this->fail($message ?: sprintf('Files "%s" and "%s" have identical contents.', $expected, $actual));
    }

    $this->addToAssertionCount(1);
  }

  /**
   * Assert that multiple files exist in a directory.
   *
   * @param string $directory
   *   The directory to check files in.
   * @param array $files
   *   Array of file names to check for existence.
   */
  public function assertFilesExist(string $directory, array $files): void {
    foreach ($files as $file) {
      $this->assertFileExists($directory . DIRECTORY_SEPARATOR . $file);
    }
  }

  /**
   * Assert that multiple files do not exist in a directory.
   *
   * @param string $directory
   *   The directory to check files in.
   * @param array $files
   *   Array of file names to check for non-existence.
   */
  public function assertFilesDoNotExist(string $directory, array $files): void {
    foreach ($files as $file) {
      $this->assertFileDoesNotExist($directory . DIRECTORY_SEPARATOR . $file);
    }
  }

  /**
   * Assert that files matching wildcard pattern(s) exist.
   *
   * @param string|array $patterns
   *   Wildcard pattern(s) to match files against.
   */
  public function assertFilesWildcardExists(string|array $patterns): void {
    $patterns = is_array($patterns) ? $patterns : [$patterns];

    if (empty($patterns)) {
      throw new \InvalidArgumentException('Empty patterns - no files to check');
    }

    foreach ($patterns as $pattern) {
      $matches = glob($pattern);

      if ($matches === FALSE) {
        // @codeCoverageIgnoreStart
        throw new \RuntimeException(sprintf('Failed to read files matching wildcard pattern: %s', $pattern));
        // @codeCoverageIgnoreEnd
      }

      $this->assertNotEmpty(
        $matches,
        sprintf('No files found matching wildcard pattern: %s', $pattern)
      );
    }
  }

  /**
   * Assert that files matching wildcard pattern(s) do not exist.
   *
   * @param string|array $patterns
   *   Wildcard pattern(s) to match files against.
   */
  public function assertFilesWildcardDoNotExist(string|array $patterns): void {
    $patterns = is_array($patterns) ? $patterns : [$patterns];

    if (empty($patterns)) {
      throw new \InvalidArgumentException('Empty patterns - no files to check');
    }

    foreach ($patterns as $pattern) {
      $matches = glob($pattern);

      if ($matches === FALSE) {
        // @codeCoverageIgnoreStart
        throw new \RuntimeException(sprintf('Failed to read files matching wildcard pattern: %s', $pattern));
        // @codeCoverageIgnoreEnd
      }

      $this->assertEmpty(
        $matches,
        sprintf('Found %d file(s) matching wildcard pattern that should not exist: %s', count($matches), $pattern)
      );
    }
  }

}
