<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\Rules;

/**
 * Benchmark for comparing directories with Rules filtering.
 */
class Compare06WithRulesBench {

  use BenchmarkDirectoryTrait;

  /**
   * Rules instance for filtering files during comparison.
   */
  protected Rules $rules;

  /**
   * Setup method - runs before each benchmark iteration (NOT timed).
   */
  public function setUp(): void {
    $this->initializeDirectories();
    $this->createIdenticalDirectories();

    $this->rules = new Rules();
    $this->rules->addIgnoreContent('/OLD_/');
  }

  /**
   * Teardown method - runs after each benchmark iteration (NOT timed).
   */
  public function tearDown(): void {
    $this->cleanupDirectories();
  }

  /**
   * Benchmark comparing directories with Rules filtering.
   *
   * @BeforeMethods("setUp")
   * @Revs(50)
   * @Warmup(2)
   * @Iterations(50)
   */
  public function benchCompare(): void {
    File::compare($this->baselineDir, $this->destinationDir, $this->rules);
  }

}
