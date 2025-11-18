<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;

/**
 * Benchmark for comparing directories with structural differences.
 */
class Compare03StructuralDiffsBench {

  use BenchmarkDirectoryTrait;

  /**
   * Setup method - runs before each benchmark iteration (NOT timed).
   */
  public function setUp(): void {
    $this->directoryInitialize();
    $this->directoryCreateIdentical();
    $this->directoryCreateWithStructuralDiffs();
  }

  /**
   * Teardown method - runs after each benchmark iteration (NOT timed).
   */
  public function tearDown(): void {
    $this->directoryCleanup();
  }

  /**
   * Benchmark comparing directories with structural differences.
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
