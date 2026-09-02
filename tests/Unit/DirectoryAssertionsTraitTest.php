<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Testing\DirectoryAssertionsTrait;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectoryAssertionsTrait::class)]
final class DirectoryAssertionsTraitTest extends TestCase {

  use DirectoryAssertionsTrait;

  protected string $testTmpDir;

  #[\Override]
  protected function setUp(): void {
    $this->testTmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('directory_assertions_test_', TRUE);
    mkdir($this->testTmpDir, 0777, TRUE);
  }

  #[\Override]
  protected function tearDown(): void {
    if (is_dir($this->testTmpDir)) {
      File::remove($this->testTmpDir);
    }
  }

  public function testAssertDirectoryContainsStringSuccess(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    $this->assertDirectoryContainsString($this->testTmpDir, 'test');
    $this->addToAssertionCount(1);

    $excluded = ['file1.txt'];
    $this->assertDirectoryContainsString($this->testTmpDir, 'another', $excluded);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryContainsStringFailure(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    try {
      $this->assertDirectoryContainsString($this->testTmpDir, 'nonexistent');
      $this->fail('Assertion should have failed for nonexistent string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "nonexistent"', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryContainsString($this->testTmpDir, 'nonexistent', [], 'Custom message for nonexistent string');
      $this->fail('Assertion should have failed for nonexistent string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for nonexistent string', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryNotContainsStringSuccess(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    $this->assertDirectoryNotContainsString($this->testTmpDir, 'nonexistent');
    $this->addToAssertionCount(1);

    $this->assertDirectoryNotContainsString($this->testTmpDir, 'test', ['file1.txt']);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryNotContainsStringFailure(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    try {
      $this->assertDirectoryNotContainsString($this->testTmpDir, 'test');
      $this->fail('Assertion should have failed for existing string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should not contain "test"', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file1.txt', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryNotContainsString($this->testTmpDir, 'test', [], 'Custom message for existing string');
      $this->fail('Assertion should have failed for existing string with custom message');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for existing string', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryContainsWordSuccess(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content with testing');

    $this->assertDirectoryContainsWord($this->testTmpDir, 'test');
    $this->addToAssertionCount(1);

    $excluded = ['file2.txt'];
    $this->assertDirectoryContainsWord($this->testTmpDir, 'test', $excluded);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryContainsWordFailure(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content with testing');

    try {
      $this->assertDirectoryContainsWord($this->testTmpDir, 'nonexistent');
      $this->fail('Assertion should have failed for nonexistent word');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "nonexistent" word', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryContainsWord($this->testTmpDir, 'nonexistent', [], 'Custom message for nonexistent word');
      $this->fail('Assertion should have failed for nonexistent word with custom message');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for nonexistent word', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryContainsWord($this->testTmpDir, 'tes');
      $this->fail('Assertion should have failed for partial word match');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "tes" word', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryNotContainsWordSuccess(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    $this->assertDirectoryNotContainsWord($this->testTmpDir, 'nonexistent');
    $this->addToAssertionCount(1);

    $this->assertDirectoryNotContainsWord($this->testTmpDir, 'tes');
    $this->addToAssertionCount(1);

    $this->assertDirectoryNotContainsWord($this->testTmpDir, 'test', ['file1.txt']);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryNotContainsWordFailure(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    try {
      $this->assertDirectoryNotContainsWord($this->testTmpDir, 'test');
      $this->fail('Assertion should have failed for existing word');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should not contain "test" word', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file1.txt', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryNotContainsWord($this->testTmpDir, 'test', [], 'Custom message for existing word');
      $this->fail('Assertion should have failed for existing word with custom message');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for existing word', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryNotContainsWord($this->testTmpDir, 'another', ['file1.txt']);
      $this->fail('Assertion should have failed for word in non-excluded file');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('file2.txt', $assertion_failed_error->getMessage());
      $this->assertStringNotContainsString('file1.txt', $assertion_failed_error->getMessage());
    }
  }

  public function testIgnoredPathsIntegrationWithContainsString(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'ignored.txt';
    $ignore_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'ignore';
    mkdir($ignore_dir);
    $file3 = $ignore_dir . DIRECTORY_SEPARATOR . 'file3.txt';

    file_put_contents($file1, 'This contains searchable content');
    file_put_contents($file2, 'This contains searchable content');
    file_put_contents($file3, 'This contains searchable content');

    $test_instance = new class() {
      use DirectoryAssertionsTrait;

      public static function ignoredPaths(): array {
        return ['ignored.txt', 'ignore'];
      }

      public function fail(string $message = ''): never {
        throw new AssertionFailedError($message);
      }

      public function addToAssertionCount(int $count): void {
        // Mock implementation for testing.
      }

    };

    $test_instance->assertDirectoryContainsString($this->testTmpDir, 'searchable');
    $this->addToAssertionCount(1);

    unlink($file1);
    try {
      $test_instance->assertDirectoryContainsString($this->testTmpDir, 'searchable');
      $this->fail('Assertion should have failed when only ignored files contain the string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "searchable"', $assertion_failed_error->getMessage());
    }
  }

  public function testIgnoredPathsIntegrationWithNotContainsString(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'ignored.txt';

    file_put_contents($file1, 'This does not contain the word');
    file_put_contents($file2, 'This contains forbidden content');

    $test_instance = new class() {
      use DirectoryAssertionsTrait;

      public static function ignoredPaths(): array {
        return ['ignored.txt'];
      }

      public function fail(string $message = ''): never {
        throw new AssertionFailedError($message);
      }

      public function addToAssertionCount(int $count): void {
        // Mock implementation for testing.
      }

    };

    $test_instance->assertDirectoryNotContainsString($this->testTmpDir, 'forbidden');
    $this->addToAssertionCount(1);

    file_put_contents($file1, 'This contains forbidden content');
    try {
      $test_instance->assertDirectoryNotContainsString($this->testTmpDir, 'forbidden');
      $this->fail('Assertion should have failed when non-ignored file contains forbidden string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should not contain "forbidden"', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file1.txt', $assertion_failed_error->getMessage());
    }
  }

  public function testIgnoredPathsIntegrationWithContainsWord(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'ignored.txt';

    file_put_contents($file1, 'This has testing words');
    file_put_contents($file2, 'This has test words');

    $test_instance = new class() {
      use DirectoryAssertionsTrait;

      public static function ignoredPaths(): array {
        return ['ignored.txt'];
      }

      public function fail(string $message = ''): never {
        throw new AssertionFailedError($message);
      }

      public function addToAssertionCount(int $count): void {
        // Mock implementation for testing.
      }

    };

    try {
      $test_instance->assertDirectoryContainsWord($this->testTmpDir, 'test');
      $this->fail('Assertion should have failed when only ignored file contains the word');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "test" word', $assertion_failed_error->getMessage());
    }
  }

  public function testIgnoredPathsIntegrationWithNotContainsWord(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'ignored.txt';

    file_put_contents($file1, 'This has safe content');
    file_put_contents($file2, 'This has forbidden word');

    $test_instance = new class() {
      use DirectoryAssertionsTrait;

      public static function ignoredPaths(): array {
        return ['ignored.txt'];
      }

      public function fail(string $message = ''): never {
        throw new AssertionFailedError($message);
      }

      public function addToAssertionCount(int $count): void {
        // Mock implementation for testing.
      }

    };

    $test_instance->assertDirectoryNotContainsWord($this->testTmpDir, 'forbidden');
    $this->addToAssertionCount(1);
  }

  public function testIgnoredPathsMergesWithExplicitExcluded(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'ignored_by_method.txt';
    $file3 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'ignored_by_override.txt';

    file_put_contents($file1, 'This contains searchable content');
    file_put_contents($file2, 'This contains searchable content');
    file_put_contents($file3, 'This contains searchable content');

    $test_instance = new class() {
      use DirectoryAssertionsTrait;

      public static function ignoredPaths(): array {
        return ['ignored_by_override.txt'];
      }

      public function fail(string $message = ''): never {
        throw new AssertionFailedError($message);
      }

      public function addToAssertionCount(int $count): void {
        // Mock implementation for testing.
      }

    };

    $test_instance->assertDirectoryContainsString($this->testTmpDir, 'searchable', ['ignored_by_method.txt']);
    $this->addToAssertionCount(1);

    unlink($file1);
    try {
      $test_instance->assertDirectoryContainsString($this->testTmpDir, 'searchable', ['ignored_by_method.txt']);
      $this->fail('Assertion should have failed when only ignored files contain the string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "searchable"', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryContainsWordWithSlashes(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This contains path/to/file and other content');
    file_put_contents($file2, 'This contains other/different but not the full path');

    $this->assertDirectoryContainsWord($this->testTmpDir, 'path/to/file');
    $this->addToAssertionCount(1);

    $this->assertDirectoryNotContainsWord($this->testTmpDir, 'path/to/nonexistent');
    $this->addToAssertionCount(1);
  }

}
