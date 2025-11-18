<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;

/**
 * Benchmark for comparing directories with large files.
 */
class Compare04LargeFilesBench {

  use BenchmarkDirectoryTrait;

  /**
   * Setup method - runs before each benchmark iteration (NOT timed).
   */
  public function setUp(): void {
    $this->directoryInitialize();
    $this->directoryCreateIdentical(6, 1, [1024, 10240, 102400, 1048576, 5242880, 10485760]);
  }

  /**
   * Teardown method - runs after each benchmark iteration (NOT timed).
   */
  public function tearDown(): void {
    $this->directoryCleanup();
  }

  /**
   * Benchmark comparing directories with large files.
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
