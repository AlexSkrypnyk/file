<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;

/**
 * Benchmark for comparing directories with content differences.
 */
class Compare02ContentDiffsBench {

  use BenchmarkDirectoryTrait;

  /**
   * Setup method - runs before each benchmark iteration (NOT timed).
   */
  public function setUp(): void {
    $this->initializeDirectories();
    $this->createIdenticalDirectories();
    $this->createDirectoryWithContentDiffs(20);
  }

  /**
   * Teardown method - runs after each benchmark iteration (NOT timed).
   */
  public function tearDown(): void {
    $this->cleanupDirectories();
  }

  /**
   * Benchmark comparing directories with content differences.
   *
   * @BeforeMethods("setUp")
   * @Revs(50)
   * @Warmup(2)
   * @Iterations(50)
   */
  public function benchCompare(): void {
    File::compare($this->baselineDir, $this->destinationDir);
  }

}
