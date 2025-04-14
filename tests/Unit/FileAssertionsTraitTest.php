<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Tests\Traits\FileAssertionsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileAssertionsTrait::class)]
class FileAssertionsTraitTest extends TestCase {

  use FileAssertionsTrait;

  protected string $tmpDir;
  protected string $testFile;

  protected function setUp(): void {
    $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_assertions_test_', TRUE);
    mkdir($this->tmpDir, 0777, TRUE);
    $this->testFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'test.txt';
  }

  protected function tearDown(): void {
    if (is_dir($this->tmpDir)) {
      File::remove($this->tmpDir);
    }
  }

  public function testAssertFileContainsStringSuccess(): void {
    file_put_contents($this->testFile, 'This is a test content');
    $this->assertFileContainsString('test', $this->testFile);
  }

  public function testAssertFileContainsStringFailure(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileContainsString('nonexistent', $this->testFile);
      $this->fail('Assertion should have failed for nonexistent string');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('should contain', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileContainsStringCustomMessage(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileContainsString('nonexistent', $this->testFile, 'Custom message for nonexistent string');
      $this->fail('Assertion should have failed for nonexistent string');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom message for nonexistent string', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileNotContainsStringSuccess(): void {
    file_put_contents($this->testFile, 'This is a test content');
    $this->assertFileNotContainsString('nonexistent', $this->testFile);
  }

  public function testAssertFileNotContainsStringFailure(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileNotContainsString('test', $this->testFile);
      $this->fail('Assertion should have failed for existing string');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('should not contain', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileNotContainsStringCustomMessage(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileNotContainsString('test', $this->testFile, 'Custom message for existing string');
      $this->fail('Assertion should have failed for existing string with custom message');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom message for existing string', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileContainsWordSuccess(): void {
    file_put_contents($this->testFile, 'This is a test content with testing');
    $this->assertFileContainsWord('test', $this->testFile);
  }

  public function testAssertFileContainsWordFailure(): void {
    file_put_contents($this->testFile, 'This is a test content with testing');

    try {
      $this->assertFileContainsWord('nonexistent', $this->testFile);
      $this->fail('Assertion should have failed for nonexistent word');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('should contain', $assertionFailedError->getMessage());
      $this->assertStringContainsString('word', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileContainsWordBoundaries(): void {
    file_put_contents($this->testFile, 'Testing test tests tester testing');

    // These should pass - these are complete words.
    $this->assertFileContainsWord('test', $this->testFile);
    $this->assertFileContainsWord('testing', $this->testFile);

    // This should fail - only a part of a word.
    try {
      $this->assertFileContainsWord('tes', $this->testFile);
      $this->fail('Assertion should have failed for partial word');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('should contain "tes" word', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileNotContainsWordSuccess(): void {
    file_put_contents($this->testFile, 'This is a test content');
    $this->assertFileNotContainsWord('nonexistent', $this->testFile);
    // Part of a word, but not a complete word - should pass.
    $this->assertFileNotContainsWord('tes', $this->testFile);
  }

  public function testAssertFileNotContainsWordFailure(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileNotContainsWord('test', $this->testFile);
      $this->fail('Assertion should have failed for existing word');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('should not contain', $assertionFailedError->getMessage());
      $this->assertStringContainsString('word', $assertionFailedError->getMessage());
    }
  }

  public function testRegexPatterns(): void {
    file_put_contents($this->testFile, 'Testing with numbers: 123 and words.');

    // These should pass - valid regex patterns.
    $this->assertFileContainsString('/\d+/', $this->testFile);
    $this->assertFileNotContainsString('/\d{4,}/', $this->testFile);

    // Regular string content should also work.
    $this->assertFileContainsString('123', $this->testFile);
    $this->assertFileNotContainsString('456', $this->testFile);
  }

  public function testAssertFileEqualsFileSuccess(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    $content = 'This is a test content';
    file_put_contents($file1, $content);
    file_put_contents($file2, $content);

    // Files with same content but different permissions should be equal.
    chmod($file1, 0644);
    chmod($file2, 0600);

    $this->assertFileEqualsFile($file1, $file2);
  }

  public function testAssertFileEqualsFileFailure(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is content for file 1');
    file_put_contents($file2, 'This is different content for file 2');

    try {
      $this->assertFileEqualsFile($file1, $file2);
      $this->fail('Assertion should have failed for different content');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('match', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileEqualsFileNonexistentFiles(): void {
    $file = $this->tmpDir . DIRECTORY_SEPARATOR . 'file.txt';
    $nonexistent = $this->tmpDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';

    file_put_contents($file, 'Some content');

    // Test with nonexistent expected file.
    try {
      $this->assertFileEqualsFile($nonexistent, $file);
      $this->fail('Assertion should have failed for nonexistent expected file');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('does not exist', $assertionFailedError->getMessage());
    }

    // Test with nonexistent actual file.
    try {
      $this->assertFileEqualsFile($file, $nonexistent);
      $this->fail('Assertion should have failed for nonexistent actual file');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('does not exist', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileNotEqualsFileSuccess(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'Content for file 1');
    file_put_contents($file2, 'Different content for file 2');

    $this->assertFileNotEqualsFile($file1, $file2);
  }

  public function testAssertFileNotEqualsFileFailure(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    $content = 'Identical content in both files';
    file_put_contents($file1, $content);
    file_put_contents($file2, $content);

    try {
      $this->assertFileNotEqualsFile($file1, $file2);
      $this->fail('Assertion should have failed for identical content');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('identical contents', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileNotEqualsFileNonexistentFiles(): void {
    $file = $this->tmpDir . DIRECTORY_SEPARATOR . 'file.txt';
    $nonexistent = $this->tmpDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';

    file_put_contents($file, 'Some content');

    // Test with nonexistent expected file.
    try {
      $this->assertFileNotEqualsFile($nonexistent, $file);
      $this->fail('Assertion should have failed for nonexistent expected file');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('does not exist', $assertionFailedError->getMessage());
    }

    // Test with nonexistent actual file.
    try {
      $this->assertFileNotEqualsFile($file, $nonexistent);
      $this->fail('Assertion should have failed for nonexistent actual file');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('does not exist', $assertionFailedError->getMessage());
    }
  }

  public function testFileComparisonCustomMessages(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'Content for file 1');
    file_put_contents($file2, 'Different content for file 2');

    // Custom message for assertFileEqualsFile.
    try {
      $this->assertFileEqualsFile($file1, $file2, 'Custom message for different files');
      $this->fail('Assertion should have failed with custom message');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom message for different files', $assertionFailedError->getMessage());
    }

    // Make files identical.
    file_put_contents($file2, 'Content for file 1');

    // Custom message for assertFileNotEqualsFile.
    try {
      $this->assertFileNotEqualsFile($file1, $file2, 'Custom message for identical files');
      $this->fail('Assertion should have failed with custom message');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom message for identical files', $assertionFailedError->getMessage());
    }
  }

}
