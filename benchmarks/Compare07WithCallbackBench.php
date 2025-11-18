<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;

/**
 * Benchmark for comparing directories with content processing callback.
 */
class Compare07WithCallbackBench {

  use BenchmarkDirectoryTrait;

  /**
   * Setup method - runs before each benchmark iteration (NOT timed).
   */
  public function setUp(): void {
    $this->directoryInitialize();
    $this->directoryCreateIdentical();
  }

  /**
   * Teardown method - runs after each benchmark iteration (NOT timed).
   */
  public function tearDown(): void {
    $this->directoryCleanup();
  }

  /**
   * Benchmark comparing directories with content processing callback.
   *
   * @BeforeMethods("setUp")
   * @Revs(50)
   * @Warmup(2)
   * @Iterations(50)
   */
  public function benchCompare(): void {
    $callback = fn(string $content): string => str_replace('File', 'Document', $content);
    File::compare($this->baselineDir, $this->destinationDir, NULL, $callback);
  }

}
