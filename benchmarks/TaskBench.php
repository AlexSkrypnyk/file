<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\ContentFile;

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
   * Setup method - creates test files (NOT timed).
   */
  public function setUp(): void {
    $this->directoryInitializeTest();
    $this->directoryCreateStructure($this->testDir);
  }

  /**
   * Teardown method - cleans up test files (NOT timed).
   */
  public function tearDown(): void {
    $this->directoryCleanup();
    File::clearDirectoryTasks();
  }

  /**
   * Benchmark traditional approach with multiple directory scans.
   *
   * Tests multiple directory scans with File::replaceContentInDir() and
   * File::removeTokenInDir() methods. This approach is simple but performs
   * multiple directory scans (one per operation).
   *
   * @BeforeMethods("setUp")
   * @AfterMethods("tearDown")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(20)
   */
  public function benchTraditionalApproach(): void {
    // Perform 10 operations, each requiring a full directory scan.
    for ($task = 1; $task <= self::TASK_COUNT; $task++) {
      File::replaceContentInDir($this->testDir, 'OLD_' . $task, 'NEW_' . $task);
      if ($task <= 5) {
        File::removeTokenInDir($this->testDir, '#; TOKEN_' . $task);
      }
    }
  }

  /**
   * Benchmark simple approach with single scan + loop.
   *
   * Tests single directory scan followed by looping through files and
   * performing operations. This approach scans once but performs multiple
   * I/O operations per file.
   *
   * @BeforeMethods("setUp")
   * @AfterMethods("tearDown")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(20)
   */
  public function benchSimpleApproach(): void {
    // Single directory scan to get all files.
    $files = File::scandir($this->testDir, File::ignoredPaths());

    // Loop through files and perform all operations.
    foreach ($files as $file) {
      // Perform 10 replacement operations.
      for ($task = 1; $task <= self::TASK_COUNT; $task++) {
        File::replaceContentInFile($file, 'OLD_' . $task, 'NEW_' . $task);
      }

      // Perform 5 token removal operations.
      for ($task = 1; $task <= 5; $task++) {
        File::removeTokenInFile($file, '#; TOKEN_' . $task);
      }
    }
  }

  /**
   * Benchmark batched approach with queue system.
   *
   * Tests single directory scan with queue system using ContentFile.
   * This approach performs single directory scan and optimized I/O (single
   * read/write per file).
   *
   * @BeforeMethods("setUp")
   * @AfterMethods("tearDown")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(20)
   */
  public function benchBatchedApproach(): void {
    // Queue all operations using ContentFile.
    for ($task = 1; $task <= self::TASK_COUNT; $task++) {
      File::addDirectoryTask(function (ContentFile $file_info) use ($task): ContentFile {
        $processed_content = File::replaceContent($file_info->getContent(), 'OLD_' . $task, 'NEW_' . $task);
        $file_info->setContent($processed_content);
        return $file_info;
      });
      if ($task <= 5) {
        File::addDirectoryTask(function (ContentFile $file_info) use ($task): ContentFile {
          $processed_content = File::removeToken($file_info->getContent(), '#; TOKEN_' . $task);
          $file_info->setContent($processed_content);
          return $file_info;
        });
      }
    }

    // Execute all tasks with single directory scan and optimized I/O.
    File::runDirectoryTasks($this->testDir);
  }

}
