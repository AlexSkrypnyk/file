<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\ContentFile\ContentFile;
use AlexSkrypnyk\File\File;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * Benchmarks comparing different task processing approaches.
 *
 * Compares three approaches for performing multiple file operations:
 * 1. Traditional: Multiple directory scans (one per operation)
 * 2. Simple: Single scan with multiple I/O operations per file
 * 3. Batched: Single scan with queue system and optimized I/O.
 */
class TaskBench {

  use BenchmarkDirectoryTrait;

  /**
   * Number of operations to perform.
   */
  protected const TASK_COUNT = 10;

  /**
   * Creates test files (not timed).
   */
  public function setUp(): void {
    $this->directoryInitializeTest();
    $this->directoryCreateStructure($this->testDir);
  }

  /**
   * Removes test files (not timed).
   */
  public function tearDown(): void {
    $this->directoryCleanup();
    File::clearDirectoryTasks();
  }

  /**
   * Benchmarks the traditional approach.
   *
   * Each operation performs its own full directory scan.
   */
  #[BeforeMethods('setUp')]
  #[AfterMethods('tearDown')]
  #[Revs(10)]
  #[Warmup(2)]
  #[Iterations(20)]
  public function benchTraditionalApproach(): void {
    for ($task = 1; $task <= self::TASK_COUNT; $task++) {
      File::replaceContentInDir($this->testDir, 'OLD_' . $task, 'NEW_' . $task);
      if ($task <= self::TOKEN_COUNT) {
        File::removeTokenInDir($this->testDir, '#; TOKEN_' . $task);
      }
    }
  }

  /**
   * Benchmarks the simple approach.
   *
   * The directory is scanned once, but each file undergoes multiple I/O
   * operations.
   */
  #[BeforeMethods('setUp')]
  #[AfterMethods('tearDown')]
  #[Revs(10)]
  #[Warmup(2)]
  #[Iterations(20)]
  public function benchSimpleApproach(): void {
    $files = File::scandir($this->testDir, File::ignoredPaths());

    foreach ($files as $file) {
      for ($task = 1; $task <= self::TASK_COUNT; $task++) {
        File::replaceContentInFile($file, 'OLD_' . $task, 'NEW_' . $task);
      }

      for ($task = 1; $task <= self::TOKEN_COUNT; $task++) {
        File::removeTokenInFile($file, '#; TOKEN_' . $task);
      }
    }
  }

  /**
   * Benchmarks the batched approach.
   *
   * Operations are queued and run after a single directory scan, with a
   * single read/write per file.
   */
  #[BeforeMethods('setUp')]
  #[AfterMethods('tearDown')]
  #[Revs(10)]
  #[Warmup(2)]
  #[Iterations(20)]
  public function benchBatchedApproach(): void {
    for ($task = 1; $task <= self::TASK_COUNT; $task++) {
      File::addDirectoryTask(function (ContentFile $file_info) use ($task): ContentFile {
        $processed_content = File::replaceContent($file_info->getContent(), 'OLD_' . $task, 'NEW_' . $task);
        $file_info->setContent($processed_content);
        return $file_info;
      });
      if ($task <= self::TOKEN_COUNT) {
        File::addDirectoryTask(function (ContentFile $file_info) use ($task): ContentFile {
          $processed_content = File::removeToken($file_info->getContent(), '#; TOKEN_' . $task);
          $file_info->setContent($processed_content);
          return $file_info;
        });
      }
    }

    File::runDirectoryTasks($this->testDir);
  }

}
