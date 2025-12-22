<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

/**
 * Generic queue management system for batch operations.
 */
class Tasker {

  /**
   * Array to store queued tasks by batch name.
   *
   * @var array<string, array<int, callable>>
   */
  protected array $queues = [];

  /**
   * Iterator functions by batch name.
   *
   * @var array<string, callable>
   */
  protected array $iterators = [];

  /**
   * Add a task to the specified batch queue.
   *
   * @param callable $callback
   *   Callback function to execute.
   * @param string $batch_name
   *   Name of the batch queue.
   */
  public function addTask(callable $callback, string $batch_name): void {
    if (!isset($this->queues[$batch_name])) {
      $this->queues[$batch_name] = [];
    }
    $this->queues[$batch_name][] = $callback;
  }

  /**
   * Set the iterator function for a specific batch.
   *
   * @param callable $iterator
   *   Iterator function that returns iterable context items.
   * @param string $batch_name
   *   Name of the batch queue.
   */
  public function setIterator(callable $iterator, string $batch_name): static {
    $this->iterators[$batch_name] = $iterator;

    return $this;
  }

  /**
   * Process all queued tasks for the specified batch.
   *
   * @param string $batch_name
   *   Name of the batch queue to process.
   */
  public function process(string $batch_name): static {
    if (!isset($this->iterators[$batch_name]) || !isset($this->queues[$batch_name])) {
      return $this;
    }

    $generator = call_user_func($this->iterators[$batch_name]);
    $tasks = $this->queues[$batch_name];

    // Verify the callable returned a Generator instance.
    if (!$generator instanceof \Generator) {
      throw new \InvalidArgumentException(sprintf(
        'Iterator callable must return a Generator instance, %s given',
        get_debug_type($generator)
      ));
    }

    // Start the generator and process each yielded item.
    $generator->rewind();
    while ($generator->valid()) {
      $context = $generator->current();

      if (!is_object($context)) {
        throw new \InvalidArgumentException(sprintf(
          'Context must be an object, %s given',
          gettype($context)
        ));
      }

      // Apply all tasks sequentially, passing the full context.
      $processed_context = $context;
      foreach ($tasks as $item_callback) {
        $processed_context = $item_callback($processed_context);

        if (!is_object($processed_context)) {
          throw new \InvalidArgumentException(sprintf(
            'Task callback must return an object, %s returned',
            gettype($processed_context)
          ));
        }
      }

      // Send the processed context back to the generator.
      $generator->send($processed_context);
    }

    // Clear the batch after execution.
    $this->clear($batch_name);

    return $this;
  }

  /**
   * Clear all tasks from the specified batch or all batches.
   *
   * @param string|null $batch_name
   *   Optional batch name. If null, clears all batches.
   */
  public function clear(?string $batch_name = NULL): static {
    if ($batch_name !== NULL) {
      unset($this->queues[$batch_name]);
      unset($this->iterators[$batch_name]);
    }
    else {
      $this->queues = [];
      $this->iterators = [];
    }

    return $this;
  }

}
