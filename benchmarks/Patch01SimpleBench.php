<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;

/**
 * Benchmark for patching a simple codebase.
 *
 * Establishes a baseline for patching performance with:
 * - 100 files across 10 directories
 * - 3 levels of directory nesting
 * - Default file sizes (small files ~1KB)
 * - 20% of files with content modifications.
 */
class Patch01SimpleBench {

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

    // Generate diff/patch files from baseline → destination comparison.
    File::diff(
      $this->baselineDir,
      $this->destinationDir,
      $this->diffDir
    );
  }

  /**
   * Teardown method - runs after each benchmark iteration (NOT timed).
   */
  public function tearDown(): void {
    $this->directoryCleanup();
  }

  /**
   * Benchmark patching a simple codebase (100 files, 10 dirs, 3 levels).
   *
   * @BeforeMethods("setUp")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(10)
   */
  public function benchPatchSimple(): void {
    // Apply patches: baseline + diff → patch_destination.
    File::patch(
      $this->baselineDir,
      $this->diffDir,
      $this->patchDestination
    );
  }

}
