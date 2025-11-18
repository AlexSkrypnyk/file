<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\Index;
use AlexSkrypnyk\File\Tests\Traits\DirectoryAssertionsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectoryAssertionsTrait::class)]
class DirectoryAssertionsTraitTest extends TestCase {

  use DirectoryAssertionsTrait;

  protected string $tmpDir;
  protected string $baselineDir;
  protected string $diffDir;
  protected string $expectedDir;
  protected string $actualDir;

  protected function setUp(): void {
    $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('directory_assertions_test_', TRUE);
    mkdir($this->tmpDir, 0777, TRUE);

    $this->baselineDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'baseline';
    $this->diffDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'diff';
    $this->expectedDir = $this->tmpDir . DIRECTORY_SEPARATOR . '.expected';
    $this->actualDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'actual';

    mkdir($this->baselineDir, 0777, TRUE);
    mkdir($this->diffDir, 0777, TRUE);
    mkdir($this->actualDir, 0777, TRUE);
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

  public function testAssertDirectoryEqualsDirectoryPositive(): void {
    $dir1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'dir1';
    $dir2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'dir2';

    mkdir($dir1, 0777, TRUE);
    mkdir($dir2, 0777, TRUE);

    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'file1.txt', 'Content 1');
    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'file2.txt', 'Content 2');

    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'file1.txt', 'Content 1');
    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'file2.txt', 'Content 2');

    $this->assertDirectoryEqualsDirectory($dir1, $dir2);
    $this->addToAssertionCount(1);

    mkdir($dir1 . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    mkdir($dir2 . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);

    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file3.txt', 'Content 3');
    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file3.txt', 'Content 3');

    $this->assertDirectoryEqualsDirectory($dir1, $dir2);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryEqualsDirectoryNegative(): void {
    $dir1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'dir1';
    $dir2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'dir2';

    mkdir($dir1, 0777, TRUE);
    mkdir($dir2, 0777, TRUE);

    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'file1.txt', 'Content 1');
    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'file2.txt', 'Content 2');

    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'file1.txt', 'Different content');
    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'file3.txt', 'Content 3');

    try {
      $this->assertDirectoryEqualsDirectory($dir1, $dir2);
      $this->fail('Assertion should have failed for different file content');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Files that differ in content', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file1.txt', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoryEqualsDirectory($dir1, $dir2, 'Custom message for missing files');
      $this->fail('Assertion should have failed for missing files');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for missing files', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('Files absent in', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file2.txt', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file3.txt', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryEqualsPatchedBaselinePositive(): void {
    mkdir($this->baselineDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file2.txt', 'Subdir content');

    mkdir($this->diffDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file3.txt', 'New file content');

    mkdir($this->actualDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file3.txt', 'New file content');
    $subdir_path = $this->actualDir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR;
    file_put_contents($subdir_path . 'file2.txt', 'Subdir content');

    $this->assertDirectoryEqualsPatchedBaseline($this->actualDir, $this->baselineDir, $this->diffDir);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryEqualsPatchedBaselineNegative(): void {
    mkdir($this->baselineDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file2.txt', 'Subdir content');

    mkdir($this->diffDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Modified content');
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file3.txt', 'New file content');

    mkdir($this->actualDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Wrong content');
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file3.txt', 'New file content');
    $subdir_path = $this->actualDir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR;
    file_put_contents($subdir_path . 'file2.txt', 'Subdir content');

    $this->expectedDir = $this->tmpDir . DIRECTORY_SEPARATOR . '.expected';
    mkdir($this->expectedDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->expectedDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Modified content');
    file_put_contents($this->expectedDir . DIRECTORY_SEPARATOR . 'file3.txt', 'New file content');
    $subdir_path = $this->expectedDir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR;
    file_put_contents($subdir_path . 'file2.txt', 'Subdir content');

    try {
      $this->assertDirectoryEqualsPatchedBaseline($this->actualDir, $this->baselineDir, $this->diffDir, $this->expectedDir);
      $this->fail('Assertion should have failed for different content');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Files that differ in content', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file1.txt', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryEqualsPatchedBaselineWithNonexistentBaseline(): void {
    $nonexistent_dir = $this->tmpDir . DIRECTORY_SEPARATOR . 'nonexistent';

    try {
      $this->assertDirectoryEqualsPatchedBaseline($this->actualDir, $nonexistent_dir, $this->diffDir);
      $this->fail('Assertion should have failed for nonexistent baseline directory');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('The baseline directory does not exist', $assertion_failed_error->getMessage());
      $this->assertStringContainsString($nonexistent_dir, $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryEqualsPatchedBaselineWithInvalidPatch(): void {
    mkdir($this->baselineDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', "line1\nline2\nline3\n");

    // Create an invalid patch file with incorrect line indices.
    mkdir($this->diffDir, 0777, TRUE);
    $diff_content = "@@ -1,3 +1,3 @@\n line1\n-wrong line\n+new line 2\n line3\n";
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', $diff_content);

    mkdir($this->actualDir, 0777, TRUE);

    try {
      $this->assertDirectoryEqualsPatchedBaseline($this->actualDir, $this->baselineDir, $this->diffDir);
      $this->fail('Assertion should have failed for invalid patch');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Failed to apply patch', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryEqualsPatchedBaselineWithHunkMismatch(): void {
    mkdir($this->baselineDir, 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', "line1\nline2\nline3\n");

    // Create a patch file with a hunk mismatch (incomplete hunk)
    mkdir($this->diffDir, 0777, TRUE);
    // Missing the rest of the hunk.
    $diff_content = "@@ -1,3 +1,3 @@\n line1\n-line2\n";
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', $diff_content);

    mkdir($this->actualDir, 0777, TRUE);

    try {
      $this->assertDirectoryEqualsPatchedBaseline($this->actualDir, $this->baselineDir, $this->diffDir);
      $this->fail('Assertion should have failed for hunk mismatch');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Failed to apply patch', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('Hunk mismatch', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryEqualsPatchedBaselineWithUnexpectedEof(): void {
    mkdir($this->baselineDir, 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', "line1\nline2\nline3\n");

    // Create a patch file with unexpected EOF.
    mkdir($this->diffDir, 0777, TRUE);
    // Missing the content completely.
    $diff_content = "@@ -1,3 +1,3 @@";
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', $diff_content);

    mkdir($this->actualDir, 0777, TRUE);

    try {
      $this->assertDirectoryEqualsPatchedBaseline($this->actualDir, $this->baselineDir, $this->diffDir);
      $this->fail('Assertion should have failed for unexpected EOF');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Failed to apply patch', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('Unexpected EOF', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertDirectoryEqualsPatchedBaselineWithIgnoreContent(): void {
    mkdir($this->baselineDir, 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . Index::IGNORECONTENT, "*.ignored\n*.log");

    mkdir($this->diffDir, 0777, TRUE);
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');

    mkdir($this->actualDir, 0777, TRUE);
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');

    $this->expectedDir = $this->tmpDir . DIRECTORY_SEPARATOR . '.expected';
    mkdir($this->expectedDir, 0777, TRUE);
    file_put_contents($this->expectedDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->expectedDir . DIRECTORY_SEPARATOR . Index::IGNORECONTENT, "*.ignored\n*.log");

    $this->assertDirectoryEqualsPatchedBaseline($this->actualDir, $this->baselineDir, $this->diffDir, $this->expectedDir);
    $this->addToAssertionCount(1);

    $expected_ignore_content_path = $this->expectedDir . DIRECTORY_SEPARATOR . Index::IGNORECONTENT;
    $this->assertFileExists($expected_ignore_content_path);
    $content = file_get_contents($expected_ignore_content_path);
    $this->assertIsString($content);
    $this->assertEquals("*.ignored\n*.log", $content);
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

  public function testAssertDirectoryEqualsPatchedBaselineWithCustomMessage(): void {
    // Set up baseline directory.
    mkdir($this->baselineDir, 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');

    // Set up diff directory.
    mkdir($this->diffDir, 0777, TRUE);
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');

    // Set up actual directory.
    mkdir($this->actualDir, 0777, TRUE);
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');

    // Test successful assertion with custom message.
    $this->assertDirectoryEqualsPatchedBaseline($this->actualDir, $this->baselineDir, $this->diffDir, NULL, 'Custom success message');
    $this->addToAssertionCount(1);

    // Test failed assertion with custom message (nonexistent baseline).
    $nonexistent_dir = $this->tmpDir . DIRECTORY_SEPARATOR . 'nonexistent';
    try {
      $this->assertDirectoryEqualsPatchedBaseline($this->actualDir, $nonexistent_dir, $this->diffDir, NULL, 'Custom failure message');
      $this->fail('Assertion should have failed');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom failure message', $assertion_failed_error->getMessage());
    }
  }

}
