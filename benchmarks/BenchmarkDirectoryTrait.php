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
   * Temporary directory for test data.
   */
  protected string $tmpDir = '';

  /**
   * Baseline directory path.
   */
  protected string $baselineDir = '';

  /**
   * Destination directory path.
   */
  protected string $destinationDir = '';

  /**
   * Number of files to create in test directories.
   */
  protected const FILE_COUNT = 100;

  /**
   * Number of subdirectories to create in test directories.
   */
  protected const DIR_COUNT = 10;

  /**
   * Maximum depth for deep nesting tests.
   */
  protected const MAX_DEPTH = 5;

  /**
   * Size of large files for testing (1 MB).
   */
  protected const LARGE_FILE_SIZE = 1048576;

  /**
   * Initialize directory structure.
   *
   * Creates temporary baseline and destination directories.
   */
  protected function initializeDirectories(): void {
    $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_bench_', TRUE);
    mkdir($this->tmpDir, 0777, TRUE);

    $this->baselineDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'baseline';
    $this->destinationDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'destination';

    mkdir($this->baselineDir, 0777, TRUE);
    mkdir($this->destinationDir, 0777, TRUE);
  }

  /**
   * Clean up test directories.
   */
  protected function cleanupDirectories(): void {
    if (is_dir($this->tmpDir)) {
      File::rmdir($this->tmpDir);
    }
  }

  /**
   * Create identical directories for baseline comparison.
   */
  protected function createIdenticalDirectories(): void {
    $files_per_dir = (int) ceil(self::FILE_COUNT / self::DIR_COUNT);
    $file_counter = 1;

    for ($dir_num = 1; $dir_num <= self::DIR_COUNT; $dir_num++) {
      $baseline_sub_dir = $this->baselineDir . DIRECTORY_SEPARATOR . ('subdir_' . $dir_num);
      $dest_sub_dir = $this->destinationDir . DIRECTORY_SEPARATOR . ('subdir_' . $dir_num);

      mkdir($baseline_sub_dir, 0777, TRUE);
      mkdir($dest_sub_dir, 0777, TRUE);

      for ($file_in_dir = 1; $file_in_dir <= $files_per_dir && $file_counter <= self::FILE_COUNT; $file_in_dir++) {
        $content = "File {$file_counter} with OLD_1 OLD_2 OLD_3 content\nLine 2\nLine 3\n";
        $filename = sprintf('file_%d.txt', $file_counter);

        file_put_contents($baseline_sub_dir . DIRECTORY_SEPARATOR . $filename, $content);
        file_put_contents($dest_sub_dir . DIRECTORY_SEPARATOR . $filename, $content);

        $file_counter++;
      }
    }
  }

  /**
   * Create directories with content differences.
   *
   * @param int $percent_changed
   *   Percentage of files to modify (0-100).
   */
  protected function createDirectoryWithContentDiffs(int $percent_changed): void {
    $files = File::scandirRecursive($this->destinationDir);
    $files_to_change = (int) ceil(count($files) * ($percent_changed / 100));

    shuffle($files);
    $files_to_change_list = array_slice($files, 0, $files_to_change);

    foreach ($files_to_change_list as $file) {
      $content = file_get_contents($file);
      if ($content !== FALSE) {
        $modified_content = $content . "\nMODIFIED CONTENT\n";
        file_put_contents($file, $modified_content);
      }
    }
  }

  /**
   * Create directories with structural differences (missing/extra files).
   */
  protected function createDirectoryWithStructuralDiffs(): void {
    $files = File::scandirRecursive($this->destinationDir);
    $files_count = count($files);

    // Remove 10% of files.
    $files_to_remove = (int) ceil($files_count * 0.1);
    shuffle($files);
    $files_to_remove_list = array_slice($files, 0, $files_to_remove);

    foreach ($files_to_remove_list as $file) {
      unlink($file);
    }

    // Add 10% extra files.
    $files_to_add = (int) ceil($files_count * 0.1);
    $sub_dirs = glob($this->destinationDir . '/subdir_*');

    for ($i = 1; $i <= $files_to_add; $i++) {
      if (empty($sub_dirs)) {
        break;
      }
      $random_dir = $sub_dirs[array_rand($sub_dirs)];
      $extra_file = $random_dir . DIRECTORY_SEPARATOR . sprintf('extra_file_%d.txt', $i);
      file_put_contents($extra_file, "Extra file {$i} content\n");
    }
  }

  /**
   * Create large files for performance testing.
   */
  protected function createLargeFiles(): void {
    $file_sizes = [1024, 10240, 102400, 1048576, 5242880, 10485760];

    foreach ($file_sizes as $index => $size) {
      $content = str_repeat("Line of text to fill the file.\n", (int) ceil($size / 30));
      $content = substr($content, 0, $size);

      $baseline_file = $this->baselineDir . DIRECTORY_SEPARATOR . sprintf('large_file_%d.txt', $index);
      $dest_file = $this->destinationDir . DIRECTORY_SEPARATOR . sprintf('large_file_%d.txt', $index);

      file_put_contents($baseline_file, $content);
      file_put_contents($dest_file, $content);
    }
  }

  /**
   * Create deeply nested directory structure.
   *
   * @param int $depth
   *   Maximum nesting depth.
   */
  protected function createDeepNestedStructure(int $depth): void {
    $files_per_level = (int) ceil(500 / $depth);

    for ($level = 1; $level <= $depth; $level++) {
      $baseline_path = $this->baselineDir;
      $dest_path = $this->destinationDir;

      for ($i = 1; $i <= $level; $i++) {
        $baseline_path .= DIRECTORY_SEPARATOR . ('level_' . $i);
        $dest_path .= DIRECTORY_SEPARATOR . ('level_' . $i);
      }

      mkdir($baseline_path, 0777, TRUE);
      mkdir($dest_path, 0777, TRUE);

      for ($file_num = 1; $file_num <= $files_per_level; $file_num++) {
        $content = sprintf('File at level %d, number %d%s', $level, $file_num, PHP_EOL);
        $filename = sprintf('file_l%d_n%d.txt', $level, $file_num);

        file_put_contents($baseline_path . DIRECTORY_SEPARATOR . $filename, $content);
        file_put_contents($dest_path . DIRECTORY_SEPARATOR . $filename, $content);
      }
    }
  }

}
