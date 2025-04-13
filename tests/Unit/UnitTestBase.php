<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Tests\Traits\DirectoryAssertionsTrait;
use AlexSkrypnyk\File\Tests\Traits\LocationsTrait;
use AlexSkrypnyk\File\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus\Error;
use PHPUnit\Framework\TestStatus\Failure;

/**
 * Class UnitTestCase.
 *
 * Base class for unit tests.
 *
 * Use DEBUG=1 to prevent cleanup of the test directory.
 *
 * @package AlexSkrypnyk\File\Tests\Unit
 */
abstract class UnitTestBase extends TestCase {

  use DirectoryAssertionsTrait;
  use ReflectionTrait;
  use LocationsTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    self::locationsInit();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (!$this->status() instanceof Failure && !$this->status() instanceof Error && !static::isDebug()) {
      self::locationsTearDown();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function onNotSuccessfulTest(\Throwable $t): never {
    fwrite(STDERR, PHP_EOL . PHP_EOL . 'Error: ' . $t->getMessage() . PHP_EOL);
    static::info();
    parent::onNotSuccessfulTest($t);
  }

  /**
   * Print additional information about the test.
   */
  protected static function info(): void {
    // Collect all methods of the class that ends with 'Info'.
    $methods = array_filter(get_class_methods(static::class), fn($m): bool => str_ends_with($m, 'Info'));
    $info = implode(PHP_EOL, array_map(fn($method): mixed => is_callable([static::class, $method]) ? static::{$method}() : '', $methods)) . PHP_EOL;

    if (!empty(trim($info))) {
      fwrite(STDERR, PHP_EOL . '-----------------------' . PHP_EOL);
      fwrite(STDERR, 'Additional information:' . PHP_EOL . PHP_EOL);
      fwrite(STDERR, $info);
      fwrite(STDERR, '-----------------------' . PHP_EOL);
    }
  }

  /**
   * Check if the test is running in debug mode.
   */
  protected static function isDebug(): bool {
    return getenv('DEBUG') || in_array('--debug', (array) ($_SERVER['argv'] ?? []));
  }

}
