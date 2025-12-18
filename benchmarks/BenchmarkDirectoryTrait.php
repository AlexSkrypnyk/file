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
   * Baseline directory path.
   */
  protected string $baselineDir = '';

  /**
   * Destination directory path.
   */
  protected string $destinationDir = '';

  /**
   * Diff directory path (for storing generated diff/patch files).
   */
  protected string $diffDir = '';

  /**
   * Patch destination directory path (for patch application target).
   */
  protected string $patchDestination = '';

  /**
   * Initialize directory structure.
   *
   * Creates temporary baseline, destination, and diff directories.
   * Sets patch destination path (created by File::patch()).
   */
  protected function directoryInitialize(): void {
    $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_bench_', TRUE);
    mkdir($this->tmpDir, 0777, TRUE);

    $this->baselineDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'baseline';
    $this->destinationDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'destination';
    $this->diffDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'diff';
    $this->patchDestination = $this->tmpDir . DIRECTORY_SEPARATOR . 'patch_destination';

    mkdir($this->baselineDir, 0777, TRUE);
    mkdir($this->destinationDir, 0777, TRUE);
    mkdir($this->diffDir, 0777, TRUE);
    // Note: patchDestination is NOT created here - created by File::patch().
  }

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
   * Cleans up patch destination directory if it exists, then removes the
   * entire temporary directory (including baseline, destination, diff).
   */
  protected function directoryCleanup(): void {
    // Clean up patch destination if it exists.
    if (!empty($this->patchDestination) && is_dir($this->patchDestination)) {
      File::rmdir($this->patchDestination);
    }

    // Clean up main tmp directory (includes all subdirectories).
    if (is_dir($this->tmpDir)) {
      File::rmdir($this->tmpDir);
    }
  }

  /**
   * Create directory structure with files containing string patterns.
   *
   * Helper method that creates files in a target directory with configurable
   * nested directory depth and optional large file sizes.
   *
   * @param string $target_dir
   *   Target directory to create files in.
   * @param int $file_count
   *   Number of files to create. Default: 100.
   * @param int $dir_count
   *   Number of subdirectories to create. Default: 10.
   * @param array $file_sizes
   *   Optional array of file sizes in bytes. When provided, files will be
   *   padded to these sizes. Default: [].
   * @param int $directory_depth
   *   Depth of nested directory structure. Default: 3.
   */
  protected function directoryCreateStructure(string $target_dir, int $file_count = 100, int $dir_count = 10, array $file_sizes = [], int $directory_depth = 3): void {
    $files_per_level = (int) ceil($file_count / $directory_depth);
    $file_counter = 1;

    for ($level = 1; $level <= $directory_depth; $level++) {
      // Build nested path: level_1/level_2/level_3/etc.
      $nested_path = $target_dir;
      for ($i = 1; $i <= $level; $i++) {
        $nested_path .= DIRECTORY_SEPARATOR . ('level_' . $i);
      }
      mkdir($nested_path, 0777, TRUE);

      for ($file_in_level = 1; $file_in_level <= $files_per_level && $file_counter <= $file_count; $file_in_level++) {
        // Build OLD_* placeholders.
        $old_parts = [];
        for ($i = 1; $i <= self::OLD_COUNT; $i++) {
          $old_parts[] = 'OLD_' . $i;
        }

        // Build TOKEN_* placeholders.
        $token_parts = [];
        for ($i = 1; $i <= self::TOKEN_COUNT; $i++) {
          $token_parts[] = '#; TOKEN_' . $i;
        }

        // Create base content with OLD_* and TOKEN_* patterns.
        $content = sprintf('File %d with ', $file_counter) . implode(' ', $old_parts) . "\n" . implode("\n", $token_parts) . "\n";

        // If file sizes are provided, pad the content to reach target size.
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

  /**
   * Create identical directories for baseline comparison.
   *
   * @param int $file_count
   *   Number of files to create. Default: 100.
   * @param int $dir_count
   *   Number of subdirectories to create. Default: 10.
   * @param array $file_sizes
   *   Optional array of file sizes in bytes. Default: [].
   * @param int $directory_depth
   *   Depth of nested directory structure. Default: 3.
   */
  protected function directoryCreateIdentical(int $file_count = 100, int $dir_count = 10, array $file_sizes = [], int $directory_depth = 3): void {
    $this->directoryCreateStructure($this->baselineDir, $file_count, $dir_count, $file_sizes, $directory_depth);
    $this->directoryCreateStructure($this->destinationDir, $file_count, $dir_count, $file_sizes, $directory_depth);
  }

  /**
   * Create directories with content differences.
   *
   * @param int $percent_changed
   *   Percentage of files to modify (0-100).
   */
  protected function directoryCreateWithContentDiffs(int $percent_changed): void {
    $files = File::scandir($this->destinationDir);
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
  protected function directoryCreateWithStructuralDiffs(): void {
    $files = File::scandir($this->destinationDir);
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

}
