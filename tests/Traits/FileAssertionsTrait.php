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
   * @param string $needle
   *   The string to search for in the file.
   * @param string $file
   *   The file to search in.
   * @param string $message
   *   Optional custom failure message.
   */
  public function assertFileContainsString(string $needle, string $file, string $message = ''): void {
    if (!File::contains($file, $needle)) {
      $this->fail($message ?: sprintf('File "%s" should contain "%s", but it does not.', $file, $needle));
    }
    $this->addToAssertionCount(1);
  }

  /**
   * Assert that a file does not contain a specific string.
   *
   * @param string $needle
   *   The string to search for in the file.
   * @param string $file
   *   The file to search in.
   * @param string $message
   *   Optional custom failure message.
   */
  public function assertFileNotContainsString(string $needle, string $file, string $message = ''): void {
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
   * @param string $needle
   *   The word to search for in the file.
   * @param string $file
   *   The file to search in.
   * @param string $message
   *   Optional custom failure message.
   */
  public function assertFileContainsWord(string $needle, string $file, string $message = ''): void {
    if (!File::contains($file, '/\b' . preg_quote($needle) . '\b/i')) {
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
   * @param string $needle
   *   The word to search for in the file.
   * @param string $file
   *   The file to search in.
   * @param string $message
   *   Optional custom failure message.
   */
  public function assertFileNotContainsWord(string $needle, string $file, string $message = ''): void {
    if (File::contains($file, '/\b' . preg_quote($needle) . '\b/i')) {
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

}
