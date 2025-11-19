<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;

/**
 * Benchmark for diff generation with a complex codebase.
 *
 * Establishes a baseline for diff generation performance with:
 * - 500 files across 50 directories
 * - 5 levels of directory nesting
 * - Mixed file sizes (1KB-50KB)
 * - 30% of files with content modifications.
 */
class Diff02ComplexBench {

  use BenchmarkDirectoryTrait;

  /**
   * Setup method - runs before each benchmark iteration (NOT timed).
   */
  public function setUp(): void {
    // Create baseline, destination, and diff directories.
    $this->directoryInitialize();

    // Create complex baseline (500 files, 50 dirs, 5 levels).
    $this->directoryCreateIdentical(
      500,
      50,
      [1024, 5120, 10240, 51200],
      5
    );

    // Create modifications in destination (30% changed).
    $this->directoryCreateWithContentDiffs(30);
  }

  /**
   * Teardown method - runs after each benchmark iteration (NOT timed).
   */
  public function tearDown(): void {
    $this->directoryCleanup();
  }

  /**
   * Benchmark diff generation for a complex codebase.
   *
   * @BeforeMethods("setUp")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(10)
   */
  public function benchDiffComplexCodebase(): void {
    // Generate diff/patch files from baseline → destination comparison.
    File::diff(
      $this->baselineDir,
      $this->destinationDir,
      $this->diffDir
    );
  }

}
