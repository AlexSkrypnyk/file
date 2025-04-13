<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Traits;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\Index;

/**
 * Assertions for testing directory contents.
 */
trait DirectoryAssertionsTrait {

  protected function assertDirectoryContainsString(string $needle, string $directory, array $excluded = [], string $message = ''): void {
    $files = File::containsInDir($directory, $needle, $excluded);

    if (empty($files)) {
      $this->fail($message ?: sprintf('Directory should contain "%s", but it does not.', $needle));
    }
  }

  protected function assertDirectoryNotContainsString(string $needle, string $directory, array $excluded = [], string $message = ''): void {
    $files = File::containsInDir($directory, $needle, $excluded);

    if (!empty($files)) {
      $this->fail($message ?: sprintf('Directory should not contain "%s", but it does within files %s.', $needle, implode(', ', $files)));
    }
  }

  protected function assertDirectoryContainsWord(string $needle, string $directory, array $excluded = [], string $message = ''): void {
    $files = File::containsInDir($directory, '/\b' . preg_quote($needle) . '\b/i', $excluded);

    if (empty($files)) {
      $this->fail($message ?: sprintf('Directory should contain "%s" word, but it does not.', $needle));
    }
  }

  protected function assertDirectoryNotContainsWord(string $needle, string $directory, array $excluded = [], string $message = ''): void {
    $files = File::containsInDir($directory, '/\b' . preg_quote($needle) . '\b/i', $excluded);

    if (!empty($files)) {
      $this->fail($message ?: sprintf('Directory should not contain "%s" word, but it does within files %s.', $needle, implode(', ', $files)));
    }
  }

  protected function assertDirectoryEqualsDirectory(string $dir1, string $dir2, ?string $message = NULL, ?callable $match_content = NULL, bool $show_diff = TRUE): void {
    $text = File::compare($dir1, $dir2, NULL, $match_content)->render(['show_diff' => $show_diff]);
    if (!empty($text)) {
      $this->fail($message ? $message . PHP_EOL . $text : $text);
    }
    $this->addToAssertionCount(1);
  }

  /**
   * Assert that a directory is equal to the patched baseline (baseline + diff).
   */
  protected function assertDirectoryEqualsPatchedBaseline(string $actual, string $baseline, string $diffs, ?string $expected = NULL): void {
    if (!is_dir($baseline)) {
      throw new \RuntimeException('The baseline directory does not exist: ' . $baseline);
    }

    // We use the .expected dir to easily assess the combined expected fixture.
    $expected = $expected ?: File::realpath($baseline . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.expected');
    File::rmdir($expected);

    File::patch($baseline, $diffs, $expected);

    // Do not override .ignorecontent file from the baseline directory.
    if (file_exists($baseline . DIRECTORY_SEPARATOR . Index::IGNORECONTENT)) {
      File::copy($baseline . DIRECTORY_SEPARATOR . Index::IGNORECONTENT, $expected . DIRECTORY_SEPARATOR . Index::IGNORECONTENT);
    }

    $this->assertDirectoryEqualsDirectory($expected, $actual);
  }

}
