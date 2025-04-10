<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReflectionTrait::class)]
class ReflectionTraitTest extends TestCase {

  use ReflectionTrait;

  protected object $testObject;

  protected function setUp(): void {
    $this->testObject = new class() {

      protected static function staticProtectedMethod(string $arg): string {
        return 'Static: ' . $arg;
      }

      protected function protectedMethod(string $arg): string {
        return 'Instance: ' . $arg;
      }

      protected string $protectedProperty = 'initial value';
    };
  }

  public function testCallProtectedMethod(): void {
    $result = self::callProtectedMethod(
      $this->testObject,
      'protectedMethod',
      ['test argument']
    );
    $this->assertSame('Instance: test argument', $result, 'Should correctly call protected instance method');

    $result = self::callProtectedMethod(
      $this->testObject::class,
      'staticProtectedMethod',
      ['test argument']
    );
    $this->assertSame('Static: test argument', $result, 'Should correctly call protected static method');
  }

  public function testCallProtectedMethodWithInvalidClass(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Class NonExistentClass does not exist');

    self::callProtectedMethod('NonExistentClass', 'someMethod');
  }

  public function testCallProtectedMethodWithInvalidMethod(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Method nonExistentMethod does not exist');

    self::callProtectedMethod($this->testObject, 'nonExistentMethod');
  }

  public function testCallProtectedMethodWithInvalidObject(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('An object instance is required for non-static methods');

    // Trying to call a non-static method via class name.
    self::callProtectedMethod($this->testObject::class, 'protectedMethod', ['test']);
  }

  public function testSetProtectedValue(): void {
    $reflection = new \ReflectionClass($this->testObject);
    $property = $reflection->getProperty('protectedProperty');
    $property->setAccessible(TRUE);
    $this->assertSame('initial value', $property->getValue($this->testObject), 'Property should have initial value');

    self::setProtectedValue($this->testObject, 'protectedProperty', 'new value');

    $this->assertSame('new value', $property->getValue($this->testObject), 'Property value should be changed by setProtectedValue');
  }

  public function testGetProtectedValue(): void {
    $reflection = new \ReflectionClass($this->testObject);
    $property = $reflection->getProperty('protectedProperty');
    $property->setAccessible(TRUE);
    $property->setValue($this->testObject, 'test value');

    $value = self::getProtectedValue($this->testObject, 'protectedProperty');

    $this->assertSame('test value', $value, 'Should correctly retrieve protected property value');
  }

}
