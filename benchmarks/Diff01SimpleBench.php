<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;

/**
 * Benchmark for diff generation with a simple codebase.
 *
 * Establishes a baseline for diff generation performance with:
 * - 100 files across 10 directories
 * - 3 levels of directory nesting
 * - Default file sizes (small files ~1KB)
 * - 20% of files with content modifications.
 */
class Diff01SimpleBench {

  use BenchmarkDirectoryTrait;

  /**
   * Setup method - runs before each benchmark iteration (NOT timed).
   */
  public function setUp(): void {
    // Create baseline, destination, and diff directories.
    $this->directoryInitialize();

    // Create simple baseline (100 files, 10 dirs, 3 levels).
    $this->directoryCreateIdentical();

    // Create modifications in destination (20% changed).
    $this->directoryCreateWithContentDiffs(20);
  }

  /**
   * Teardown method - runs after each benchmark iteration (NOT timed).
   */
  public function tearDown(): void {
    $this->directoryCleanup();
  }

  /**
   * Benchmark diff generation for a simple codebase.
   *
   * @BeforeMethods("setUp")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(10)
   */
  public function benchDiffSimple(): void {
    // Generate diff/patch files from baseline → destination comparison.
    File::diff(
      $this->baselineDir,
      $this->destinationDir,
      $this->diffDir
    );
  }

}
