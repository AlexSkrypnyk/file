<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Testing\DirectoryAssertionsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectoryAssertionsTrait::class)]
class DirectoryAssertionsTraitTest extends TestCase {

  use DirectoryAssertionsTrait;

  protected string $tmpDir;

  protected function setUp(): void {
    $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('directory_assertions_test_', TRUE);
    mkdir($this->tmpDir, 0777, TRUE);
  }

  protected function tearDown(): void {
    if (is_dir($this->tmpDir)) {
      File::remove($this->tmpDir);
    }
  }

  public function testAssertDirectoryContainsStringPositive(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    $this->assertDirectoryContainsString($this->tmpDir, 'test');
    $this->addToAssertionCount(1);

    $excluded = ['file1.txt'];
    $this->assertDirectoryContainsString($this->tmpDir, 'another', $excluded);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryContainsStringNegative(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    try {
      $this->assertDirectoryContainsString($this->tmpDir, 'nonexistent');
      $this->fail('Assertion should have failed for nonexistent string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "nonexistent"', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryContainsString($this->tmpDir, 'nonexistent', [], 'Custom message for nonexistent string');
      $this->fail('Assertion should have failed for nonexistent string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for nonexistent string', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryNotContainsStringPositive(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    $this->assertDirectoryNotContainsString($this->tmpDir, 'nonexistent');
    $this->addToAssertionCount(1);

    $this->assertDirectoryNotContainsString($this->tmpDir, 'test', ['file1.txt']);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryNotContainsStringNegative(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    try {
      $this->assertDirectoryNotContainsString($this->tmpDir, 'test');
      $this->fail('Assertion should have failed for existing string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should not contain "test"', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file1.txt', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryNotContainsString($this->tmpDir, 'test', [], 'Custom message for existing string');
      $this->fail('Assertion should have failed for existing string with custom message');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for existing string', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryContainsWordPositive(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content with testing');

    $this->assertDirectoryContainsWord($this->tmpDir, 'test');
    $this->addToAssertionCount(1);

    $excluded = ['file2.txt'];
    $this->assertDirectoryContainsWord($this->tmpDir, 'test', $excluded);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryContainsWordNegative(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content with testing');

    try {
      $this->assertDirectoryContainsWord($this->tmpDir, 'nonexistent');
      $this->fail('Assertion should have failed for nonexistent word');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "nonexistent" word', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryContainsWord($this->tmpDir, 'nonexistent', [], 'Custom message for nonexistent word');
      $this->fail('Assertion should have failed for nonexistent word with custom message');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for nonexistent word', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryContainsWord($this->tmpDir, 'tes');
      $this->fail('Assertion should have failed for partial word match');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "tes" word', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryNotContainsWordPositive(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    $this->assertDirectoryNotContainsWord($this->tmpDir, 'nonexistent');
    $this->addToAssertionCount(1);

    $this->assertDirectoryNotContainsWord($this->tmpDir, 'tes');
    $this->addToAssertionCount(1);

    $this->assertDirectoryNotContainsWord($this->tmpDir, 'test', ['file1.txt']);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryNotContainsWordNegative(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    try {
      $this->assertDirectoryNotContainsWord($this->tmpDir, 'test');
      $this->fail('Assertion should have failed for existing word');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should not contain "test" word', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file1.txt', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryNotContainsWord($this->tmpDir, 'test', [], 'Custom message for existing word');
      $this->fail('Assertion should have failed for existing word with custom message');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for existing word', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryNotContainsWord($this->tmpDir, 'another', ['file1.txt']);
      $this->fail('Assertion should have failed for word in non-excluded file');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('file2.txt', $assertion_failed_error->getMessage());
      $this->assertStringNotContainsString('file1.txt', $assertion_failed_error->getMessage());
    }
  }

  public function testIgnoredPathsIntegrationWithContainsString(): void {
    // Create test files including some that should be ignored.
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'ignored.txt';
    $ignore_dir = $this->tmpDir . DIRECTORY_SEPARATOR . 'ignore';
    mkdir($ignore_dir);
    $file3 = $ignore_dir . DIRECTORY_SEPARATOR . 'file3.txt';

    file_put_contents($file1, 'This contains searchable content');
    file_put_contents($file2, 'This contains searchable content');
    file_put_contents($file3, 'This contains searchable content');

    // Create a mock trait that overrides ignoredPaths().
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

    // Test that ignored files are excluded from search.
    $test_instance->assertDirectoryContainsString($this->tmpDir, 'searchable');
    $this->addToAssertionCount(1);

    // Remove the non-ignored file to test that assertion fails when only
    // ignored files contain the string.
    unlink($file1);
    try {
      $test_instance->assertDirectoryContainsString($this->tmpDir, 'searchable');
      $this->fail('Assertion should have failed when only ignored files contain the string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "searchable"', $assertion_failed_error->getMessage());
    }
  }

  public function testIgnoredPathsIntegrationWithNotContainsString(): void {
    // Create test files including some that should be ignored.
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'ignored.txt';

    file_put_contents($file1, 'This does not contain the word');
    file_put_contents($file2, 'This contains forbidden content');

    // Create a mock trait that overrides ignoredPaths().
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

    // Test that ignored files are excluded from search - should pass because
    // ignored file is not checked.
    $test_instance->assertDirectoryNotContainsString($this->tmpDir, 'forbidden');
    $this->addToAssertionCount(1);

    // Add forbidden content to non-ignored file to test failure.
    file_put_contents($file1, 'This contains forbidden content');
    try {
      $test_instance->assertDirectoryNotContainsString($this->tmpDir, 'forbidden');
      $this->fail('Assertion should have failed when non-ignored file contains forbidden string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should not contain "forbidden"', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file1.txt', $assertion_failed_error->getMessage());
    }
  }

  public function testIgnoredPathsIntegrationWithContainsWord(): void {
    // Create test files.
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'ignored.txt';

    file_put_contents($file1, 'This has testing words');
    file_put_contents($file2, 'This has test words');

    // Create a mock trait that overrides ignoredPaths().
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

    // Test finding complete word - should fail because ignored file is not
    // checked.
    try {
      $test_instance->assertDirectoryContainsWord($this->tmpDir, 'test');
      $this->fail('Assertion should have failed when only ignored file contains the word');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "test" word', $assertion_failed_error->getMessage());
    }
  }

  public function testIgnoredPathsIntegrationWithNotContainsWord(): void {
    // Create test files.
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'ignored.txt';

    file_put_contents($file1, 'This has safe content');
    file_put_contents($file2, 'This has forbidden word');

    // Create a mock trait that overrides ignoredPaths().
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

    // Test that ignored file is not checked - should pass.
    $test_instance->assertDirectoryNotContainsWord($this->tmpDir, 'forbidden');
    $this->addToAssertionCount(1);
  }

  public function testIgnoredPathsMergesWithExplicitExcluded(): void {
    // Create test files.
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'ignored_by_method.txt';
    $file3 = $this->tmpDir . DIRECTORY_SEPARATOR . 'ignored_by_override.txt';

    file_put_contents($file1, 'This contains searchable content');
    file_put_contents($file2, 'This contains searchable content');
    file_put_contents($file3, 'This contains searchable content');

    // Create a mock trait that overrides ignoredPaths().
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

    // Test that both ignoredPaths() and explicit excluded are merged.
    $test_instance->assertDirectoryContainsString($this->tmpDir, 'searchable', ['ignored_by_method.txt']);
    $this->addToAssertionCount(1);

    // Remove the non-ignored file and verify assertion fails.
    unlink($file1);
    try {
      $test_instance->assertDirectoryContainsString($this->tmpDir, 'searchable', ['ignored_by_method.txt']);
      $this->fail('Assertion should have failed when only ignored files contain the string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Directory should contain "searchable"', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryContainsWordWithSlashes(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This contains path/to/file and other content');
    file_put_contents($file2, 'This contains other/different but not the full path');

    // Test that needles containing forward slashes work correctly.
    $this->assertDirectoryContainsWord($this->tmpDir, 'path/to/file');
    $this->addToAssertionCount(1);

    // Test that non-existent paths with slashes don't match.
    $this->assertDirectoryNotContainsWord($this->tmpDir, 'path/to/nonexistent');
    $this->addToAssertionCount(1);
  }

}
