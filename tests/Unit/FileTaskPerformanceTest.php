<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\ExtendedSplFileInfo;
use AlexSkrypnyk\PhpunitHelpers\Traits\LoggerTrait;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

#[CoversClass(File::class)]
#[CoversMethod(File::class, 'addTaskDirectory')]
#[CoversMethod(File::class, 'runTaskDirectory')]
#[CoversMethod(File::class, 'clearTaskDirectory')]
#[CoversMethod(File::class, 'getTasker')]
#[CoversMethod(File::class, 'replaceContentInDir')]
#[CoversMethod(File::class, 'removeTokenInDir')]
#[CoversMethod(File::class, 'replaceContent')]
#[CoversMethod(File::class, 'removeToken')]
class FileTaskPerformanceTest extends UnitTestCase {

  use LoggerTrait;

  protected string $testTmpDir;
  protected const FILE_COUNT = 5000;
  protected const DIR_COUNT = 100;
  protected const TASK_COUNT = 10;

  #[\Override]
  protected function setUp(): void {
    $this->testTmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_perf_test_', TRUE);
    mkdir($this->testTmpDir, 0777, TRUE);
  }

  #[\Override]
  protected function tearDown(): void {
    if (is_dir($this->testTmpDir)) {
      File::rmdir($this->testTmpDir);
    }
    File::clearTaskDirectory();
  }

  public function testMassivePerformanceComparison(): void {
    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'performance_test';
    mkdir($test_dir, 0777);

    $this->loggerSetVerbose(TRUE);
    $this->log("Setting up performance test with " . self::FILE_COUNT . " files across " . self::DIR_COUNT . " directories");

    // Create directory structure and files.
    $this->createTestFiles($test_dir);

    // Test 1: Traditional approach - multiple directory scans using *InDir
    // methods.
    $this->log("Starting traditional approach test (using *InDir methods)...");
    $traditional_duration = $this->runTraditionalApproach($test_dir);

    // Reset files for second test.
    $this->log("Resetting files for simple loop approach test...");
    $this->createTestFiles($test_dir);

    // Test 2: Simple approach - single scan + loop operations.
    $this->log("Starting simple approach test (single scan + loop)...");
    $simple_duration = $this->runSimpleApproach($test_dir);

    // Reset files for third test.
    $this->log("Resetting files for batched approach test...");
    $this->createTestFiles($test_dir);

    // Test 3: Batched approach - single directory scan with queue system.
    $this->log("Starting batched approach test (using queue system)...");
    $batched_duration = $this->runBatchedApproach($test_dir);

    // Calculate and log performance results.
    $this->logPerformanceResults($traditional_duration, $simple_duration, $batched_duration);

    // Verify all approaches produced the same results.
    $this->verifyResults($test_dir);
  }

  protected function createTestFiles(string $test_dir): void {
    // Clean up existing files.
    if (is_dir($test_dir)) {
      File::rmdir($test_dir);
    }
    mkdir($test_dir, 0777);

    $files_per_dir = ceil(self::FILE_COUNT / self::DIR_COUNT);
    $file_counter = 1;

    for ($dir_num = 1; $dir_num <= self::DIR_COUNT; $dir_num++) {
      $sub_dir = $test_dir . DIRECTORY_SEPARATOR . ('subdir_' . $dir_num);
      mkdir($sub_dir, 0777);

      for ($file_in_dir = 1; $file_in_dir <= $files_per_dir && $file_counter <= self::FILE_COUNT; $file_in_dir++) {
        $content = "File {$file_counter} with OLD_1 OLD_2 OLD_3 OLD_4 OLD_5 OLD_6 OLD_7 OLD_8 OLD_9 OLD_10\n#; TOKEN_1\n#; TOKEN_2\n#; TOKEN_3\n#; TOKEN_4\n#; TOKEN_5\nMore content";
        file_put_contents($sub_dir . DIRECTORY_SEPARATOR . sprintf('file_%d.txt', $file_counter), $content);
        $file_counter++;
      }
    }

    $this->log("Created " . self::FILE_COUNT . " files across " . self::DIR_COUNT . " directories");
  }

  protected function runTraditionalApproach(string $test_dir): float {
    $start_time = microtime(TRUE);

    // Perform 10 different operations, each requiring a full directory scan.
    for ($task = 1; $task <= self::TASK_COUNT; $task++) {
      File::replaceContentInDir($test_dir, 'OLD_' . $task, 'NEW_' . $task);
      if ($task <= 5) {
        File::removeTokenInDir($test_dir, '#; TOKEN_' . $task);
      }
    }

    $end_time = microtime(TRUE);
    return $end_time - $start_time;
  }

  protected function runSimpleApproach(string $test_dir): float {
    $start_time = microtime(TRUE);

    // Single directory scan to get all files.
    $files = File::scandirRecursive($test_dir, File::ignoredPaths());

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

    $end_time = microtime(TRUE);
    return $end_time - $start_time;
  }

  protected function runBatchedApproach(string $test_dir): float {
    $start_time = microtime(TRUE);

    // Queue all operations using ExtendedSplFileInfo and string-based methods.
    for ($task = 1; $task <= self::TASK_COUNT; $task++) {
      File::addTaskDirectory(function (ExtendedSplFileInfo $file_info) use ($task): ExtendedSplFileInfo {
        $processed_content = File::replaceContent($file_info->getContent(), 'OLD_' . $task, 'NEW_' . $task);
        $file_info->setContent($processed_content);
        return $file_info;
      });
      if ($task <= 5) {
        File::addTaskDirectory(function (ExtendedSplFileInfo $file_info) use ($task): ExtendedSplFileInfo {
          $processed_content = File::removeToken($file_info->getContent(), '#; TOKEN_' . $task);
          $file_info->setContent($processed_content);
          return $file_info;
        });
      }
    }

    // Execute all tasks with single directory scan and optimized I/O.
    File::runTaskDirectory($test_dir);

    $end_time = microtime(TRUE);
    return $end_time - $start_time;
  }

  protected function logPerformanceResults(float $traditional_duration, float $simple_duration, float $batched_duration): void {
    $traditional_vs_simple = (($traditional_duration - $simple_duration) / $traditional_duration) * 100;
    $simple_vs_batched = (($simple_duration - $batched_duration) / $simple_duration) * 100;
    $traditional_vs_batched = (($traditional_duration - $batched_duration) / $traditional_duration) * 100;

    $performance_message = sprintf(
      PHP_EOL . '=================== PERFORMANCE COMPARISON RESULTS ===================' . PHP_EOL .
      'Test Configuration:' . PHP_EOL .
      '- Files: %d across %d directories' . PHP_EOL .
      '- Tasks: %d operations per file' . PHP_EOL . PHP_EOL .
      'Results:' . PHP_EOL .
      '- Traditional approach (*InDir methods): %.6fs (15 directory scans, multiple I/O per file)' . PHP_EOL .
      '- Simple approach (single scan + loop): %.6fs (1 directory scan, multiple I/O per file)' . PHP_EOL .
      '- Batched approach (ExtendedSplFileInfo + queue): %.6fs (1 directory scan, single I/O per file)' . PHP_EOL . PHP_EOL .
      'Performance Analysis:' . PHP_EOL .
      '- Traditional vs Simple: %.2f%% (%s faster)' . PHP_EOL .
      '- Simple vs Batched: %.2f%% (%s faster)' . PHP_EOL .
      '- Traditional vs Batched: %.2f%% (%s faster)' . PHP_EOL . PHP_EOL .
      'Key Insights:' . PHP_EOL .
      '- Directory scan overhead: %.6fs (Traditional vs Simple)' . PHP_EOL .
      '- I/O optimization benefit: %.6fs (Simple vs Batched)' . PHP_EOL .
      '- Total improvement: %.6fs (Traditional vs Batched)' . PHP_EOL .
      '=======================================================================' . PHP_EOL,
      self::FILE_COUNT,
      self::DIR_COUNT,
      self::TASK_COUNT,
      $traditional_duration,
      $simple_duration,
      $batched_duration,
      abs($traditional_vs_simple),
      $traditional_vs_simple > 0 ? 'Simple' : 'Traditional',
      abs($simple_vs_batched),
      $simple_vs_batched > 0 ? 'Batched' : 'Simple',
      abs($traditional_vs_batched),
      $traditional_vs_batched > 0 ? 'Batched' : 'Traditional',
      abs($traditional_duration - $simple_duration),
      abs($simple_duration - $batched_duration),
      abs($traditional_duration - $batched_duration)
    );

    $this->log($performance_message);

    // Assert that performance results are reasonable.
    $this->assertGreaterThan(0, $traditional_duration, 'Traditional duration should be positive');
    $this->assertGreaterThan(0, $simple_duration, 'Simple duration should be positive');
    $this->assertGreaterThan(0, $batched_duration, 'Batched duration should be positive');
    $this->assertGreaterThan(0, count([$traditional_duration, $simple_duration, $batched_duration]), 'Should have performance data');
  }

  protected function verifyResults(string $test_dir): void {
    $this->log("Verifying that both approaches produced identical results...");

    $files = File::scandirRecursive($test_dir, File::ignoredPaths());
    $verified_count = 0;

    foreach ($files as $file) {
      $content = file_get_contents($file);
      $this->assertIsString($content, 'File content should be readable');

      // Check that all replacements were made.
      for ($iteration = 1; $iteration <= 10; $iteration++) {
        $this->assertStringContainsString('NEW_' . $iteration, $content, 'File should contain replacement for OLD_' . $iteration);
        $this->assertStringNotContainsString('OLD_' . $iteration, $content, 'File should not contain original OLD_' . $iteration);
      }

      // Check that first 5 tokens were removed.
      for ($token_num = 1; $token_num <= 5; $token_num++) {
        $this->assertStringNotContainsString('#; TOKEN_' . $token_num, $content, sprintf('Token %d should be removed', $token_num));
      }

      $verified_count++;
    }

    $this->assertSame(self::FILE_COUNT, $verified_count, "All " . self::FILE_COUNT . " files should be verified");
    $this->log(sprintf('Successfully verified all %d files', $verified_count));
  }

}
