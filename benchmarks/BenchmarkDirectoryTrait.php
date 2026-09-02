<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;

/**
 * Trait for common benchmark directory operations.
 *
 * Provides helper methods for creating test directory structures
 * used across multiple benchmark classes.
 */
trait BenchmarkDirectoryTrait {

  /**
   * Number of OLD_* placeholders per file.
   */
  protected const OLD_COUNT = 10;

  /**
   * Number of TOKEN_* placeholders per file.
   */
  protected const TOKEN_COUNT = 5;

  /**
   * Temporary directory for test data.
   */
  protected string $tmpDir = '';

  /**
   * Test directory path (for single-directory benchmarks).
   */
  protected string $testDir = '';

  /**
   * Initialize single test directory structure.
   *
   * Creates temporary test directory for single-directory benchmarks.
   */
  protected function directoryInitializeTest(): void {
    $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_bench_', TRUE);
    mkdir($this->tmpDir, 0777, TRUE);

    $this->testDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'test';
    mkdir($this->testDir, 0777, TRUE);
  }

  /**
   * Clean up test directories.
   *
   * Removes the entire temporary directory.
   */
  protected function directoryCleanup(): void {
    if (is_dir($this->tmpDir)) {
      File::rmdir($this->tmpDir);
    }
  }

  /**
   * Create directory structure with files containing string patterns.
   *
   * @param string $target_dir
   *   Target directory to create files in.
   * @param int $file_count
   *   Number of files to create. Default: 100.
   * @param array $file_sizes
   *   Optional array of file sizes in bytes. When provided, files will be
   *   padded to these sizes. Default: [].
   * @param int $directory_depth
   *   Depth of nested directory structure. Default: 3.
   */
  protected function directoryCreateStructure(string $target_dir, int $file_count = 100, array $file_sizes = [], int $directory_depth = 3): void {
    $files_per_level = (int) ceil($file_count / $directory_depth);
    $file_counter = 1;

    for ($level = 1; $level <= $directory_depth; $level++) {
      $nested_path = $target_dir;

      for ($i = 1; $i <= $level; $i++) {
        $nested_path .= DIRECTORY_SEPARATOR . ('level_' . $i);
      }

      mkdir($nested_path, 0777, TRUE);

      for ($file_in_level = 1; $file_in_level <= $files_per_level && $file_counter <= $file_count; $file_in_level++) {
        $old_parts = [];
        for ($i = 1; $i <= self::OLD_COUNT; $i++) {
          $old_parts[] = 'OLD_' . $i;
        }

        $token_parts = [];
        for ($i = 1; $i <= self::TOKEN_COUNT; $i++) {
          $token_parts[] = '#; TOKEN_' . $i;
        }

        $content = sprintf('File %d with ', $file_counter) . implode(' ', $old_parts) . "\n" . implode("\n", $token_parts) . "\n";

        if (!empty($file_sizes)) {
          $size_index = ($file_counter - 1) % count($file_sizes);
          $target_size = $file_sizes[$size_index];

          if (strlen($content) < $target_size) {
            $padding = str_repeat("Line of text to fill the file.\n", (int) ceil(($target_size - strlen($content)) / 30));
            $content .= $padding;
            $content = substr($content, 0, $target_size);
          }
        }

        $filename = sprintf('file_%d.txt', $file_counter);
        file_put_contents($nested_path . DIRECTORY_SEPARATOR . $filename, $content);
        $file_counter++;
      }
    }
  }

}
