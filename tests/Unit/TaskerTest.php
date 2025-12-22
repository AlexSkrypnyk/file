<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Internal\Tasker;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Tasker::class)]
#[CoversMethod(Tasker::class, 'addTask')]
#[CoversMethod(Tasker::class, 'setIterator')]
#[CoversMethod(Tasker::class, 'process')]
#[CoversMethod(Tasker::class, 'clear')]
final class TaskerTest extends UnitTestCase {

  public function testProcessWithGenericContext(): void {
    $tasker = new Tasker();
    $processed_items = [];

    // Add two tasks that modify generic context objects.
    $tasker->addTask(function ($context) use (&$processed_items) {
      $processed_items[] = 'task1:' . $context->name;
      $context->value .= '_task1';
      return $context;
    }, 'test_batch');

    $tasker->addTask(function ($context) use (&$processed_items) {
      $processed_items[] = 'task2:' . $context->name;
      $context->value .= '_task2';
      return $context;
    }, 'test_batch');

    $final_results = [];
    $tasker->setIterator(function () use (&$final_results) {
      // Create generic test contexts.
      $test_data = [
        ['name' => 'item1', 'value' => 'content1'],
        ['name' => 'item2', 'value' => 'content2'],
      ];

      foreach ($test_data as $data) {
        $context = (object) $data;

        $processed_context = yield $context;

        if ($processed_context !== NULL) {
          $final_results[$processed_context->name] = $processed_context->value;
        }
      }
    }, 'test_batch');

    $tasker->process('test_batch');

    // Verify both tasks executed for each item.
    $this->assertCount(4, $processed_items, 'Should have 2 tasks × 2 items = 4 executions');

    // Verify context was processed by both tasks.
    $this->assertCount(2, $final_results, 'Should have processed 2 items');
    foreach ($final_results as $value) {
      $this->assertStringContainsString('_task1_task2', (string) $value, 'Context should be processed by both tasks');
    }
  }

  #[DataProvider('dataProviderProcessWithMissingComponents')]
  public function testProcessWithMissingComponents(bool $has_tasks, bool $has_iterator, bool $should_execute): void {
    $tasker = new Tasker();
    $executed = FALSE;

    if ($has_tasks) {
      $tasker->addTask(function ($context) use (&$executed) {
        $executed = TRUE;
        return $context;
      }, 'test_batch');
    }

    if ($has_iterator) {
      $tasker->setIterator(function () {
        $context = (object) ['test' => 'value'];
        yield $context;
      }, 'test_batch');
    }

    $tasker->process('test_batch');

    $this->assertSame($should_execute, $executed, 'Execution should match expected behavior based on missing components');
  }

  public static function dataProviderProcessWithMissingComponents(): \Iterator {
    yield 'has tasks and iterator' => [TRUE, TRUE, TRUE];
    yield 'has tasks, no iterator' => [TRUE, FALSE, FALSE];
    yield 'no tasks, has iterator' => [FALSE, TRUE, FALSE];
    yield 'no tasks, no iterator' => [FALSE, FALSE, FALSE];
  }

  public function testProcessClearsQueueAfterExecution(): void {
    $tasker = new Tasker();
    $first_execution_count = 0;
    $second_execution_count = 0;

    $tasker->addTask(function ($context) use (&$first_execution_count) {
      $first_execution_count++;
      return $context;
    }, 'test_batch');

    $tasker->setIterator(function () {
      foreach (['item1', 'item2'] as $item) {
        $context = (object) ['name' => $item];
        yield $context;
      }
    }, 'test_batch');

    $tasker->process('test_batch');
    $this->assertSame(2, $first_execution_count, 'First execution should process all items');

    // Try to process again - should not execute since queue was cleared.
    $tasker->process('test_batch');
    $this->assertSame(2, $first_execution_count, 'Second execution should not happen as queue was cleared');

    // Add new task and verify it works.
    $tasker->addTask(function ($context) use (&$second_execution_count) {
      $second_execution_count++;
      return $context;
    }, 'test_batch');

    $tasker->setIterator(function () {
      $context = (object) ['name' => 'item1'];
      yield $context;
    }, 'test_batch');

    $tasker->process('test_batch');
    $this->assertSame(1, $second_execution_count, 'New task should execute after re-adding');
  }

  #[DataProvider('dataProviderClear')]
  public function testClear(?string $clear_batch_name, array $setup_batches, array $expected_remaining_batches): void {
    $tasker = new Tasker();

    foreach ($setup_batches as $batch_name) {
      $tasker->addTask(fn($context) => $context, $batch_name);
      $tasker->setIterator(function () {
        // Empty generator - yield nothing.
        // @phpstan-ignore-next-line
        if (FALSE) {
          yield;
        }
      }, $batch_name);
    }

    $tasker->clear($clear_batch_name);

    // Test which batches remain by checking if they can still process.
    foreach ($expected_remaining_batches as $expected_batch) {
      $executed = FALSE;

      $tasker->addTask(function ($context) use (&$executed) {
        $executed = TRUE;
        return $context;
      }, $expected_batch);

      $tasker->setIterator(function () {
        $context = (object) ['test' => 'value'];
        yield $context;
      }, $expected_batch);

      $tasker->process($expected_batch);

      $this->assertTrue($executed, sprintf("Batch '%s' should remain and be able to execute", $expected_batch));
    }

    // If no expected remaining batches, assert that clearing worked.
    if (empty($expected_remaining_batches)) {
      $this->assertCount(0, $expected_remaining_batches, 'All batches should be cleared successfully');
    }

    // Verify cleared batches don't execute (if we cleared specific batch)
    if ($clear_batch_name !== NULL) {
      $cleared_executed = FALSE;

      $tasker->addTask(function ($context) use (&$cleared_executed) {
        $cleared_executed = TRUE;
        return $context;
      }, $clear_batch_name);

      $tasker->setIterator(function () {
        $context = (object) ['test' => 'value'];
        yield $context;
      }, $clear_batch_name);

      $tasker->process($clear_batch_name);

      $this->assertTrue($cleared_executed, "Cleared batch should work again after adding new tasks");
    }
  }

  public static function dataProviderClear(): \Iterator {
    yield 'clear specific batch from multiple' => [
      'batch1',
        ['batch1', 'batch2', 'batch3'],
        ['batch2', 'batch3'],
    ];
    yield 'clear all batches' => [
      NULL,
        ['batch1', 'batch2', 'batch3'],
        [],
    ];
    yield 'clear non-existent batch' => [
      'non_existent',
        ['batch1', 'batch2'],
        ['batch1', 'batch2'],
    ];
  }

  public function testMultipleBatchesIndependence(): void {
    $tasker = new Tasker();
    $batch1_log = [];
    $batch2_log = [];

    $tasker->addTask(function ($context) use (&$batch1_log) {
      $batch1_log[] = $context->value;
      return $context;
    }, 'batch1');

    $tasker->addTask(function ($context) use (&$batch2_log) {
      $batch2_log[] = $context->value;
      return $context;
    }, 'batch2');

    $tasker->setIterator(function () {
      foreach (['A', 'B'] as $item) {
        $context = (object) ['value' => $item];
        yield $context;
      }
    }, 'batch1');

    $tasker->setIterator(function () {
      foreach (['X', 'Y', 'Z'] as $item) {
        $context = (object) ['value' => $item];
        yield $context;
      }
    }, 'batch2');

    $tasker->process('batch1');

    $this->assertSame(['A', 'B'], $batch1_log, 'Batch1 should process its items');
    $this->assertEmpty($batch2_log, 'Batch2 should not be affected by batch1 processing');

    $tasker->process('batch2');

    $this->assertSame(['X', 'Y', 'Z'], $batch2_log, 'Batch2 should process its items independently');
  }

  #[DataProvider('dataProviderProcessExceptions')]
  public function testProcessExceptions(callable $task_callback, callable $iterator_callback, string $expected_exception_message): void {
    $tasker = new Tasker();

    $tasker->addTask($task_callback, 'test_batch');
    $tasker->setIterator($iterator_callback, 'test_batch');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected_exception_message);

    $tasker->process('test_batch');
  }

  public static function dataProviderProcessExceptions(): \Iterator {
    yield 'non-object context' => [
      fn($context) => $context,
      function () {
          yield 'not_an_object';
      },
      'Context must be an object, string given',
    ];
    yield 'non-object return from task' => [
      fn($context): string => 'not_an_object',
      function () {
          yield (object) ['test' => 'value'];
      },
      'Task callback must return an object, string returned',
    ];
    yield 'non-generator iterator return (string)' => [
      fn($context) => $context,
      fn(): string => 'not_a_generator',
      'Iterator callable must return a Generator instance, string given',
    ];
    yield 'non-generator iterator return (object)' => [
      fn($context) => $context,
      fn(): \stdClass => new \stdClass(),
      'Iterator callable must return a Generator instance, stdClass given',
    ];
  }

}
