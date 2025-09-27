<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
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
    $this->assertFileContainsString($this->testFile, 'test');
  }

  public function testAssertFileContainsStringFailure(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileContainsString($this->testFile, 'nonexistent');
      $this->fail('Assertion should have failed for nonexistent string');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('should contain', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileContainsStringCustomMessage(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileContainsString($this->testFile, 'nonexistent', 'Custom message for nonexistent string');
      $this->fail('Assertion should have failed for nonexistent string');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom message for nonexistent string', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileNotContainsStringSuccess(): void {
    file_put_contents($this->testFile, 'This is a test content');
    $this->assertFileNotContainsString($this->testFile, 'nonexistent');
  }

  public function testAssertFileNotContainsStringFailure(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileNotContainsString($this->testFile, 'test');
      $this->fail('Assertion should have failed for existing string');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('should not contain', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileNotContainsStringCustomMessage(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileNotContainsString($this->testFile, 'test', 'Custom message for existing string');
      $this->fail('Assertion should have failed for existing string with custom message');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom message for existing string', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileContainsWordSuccess(): void {
    file_put_contents($this->testFile, 'This is a test content with testing');
    $this->assertFileContainsWord($this->testFile, 'test');
  }

  public function testAssertFileContainsWordFailure(): void {
    file_put_contents($this->testFile, 'This is a test content with testing');

    try {
      $this->assertFileContainsWord($this->testFile, 'nonexistent');
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
    $this->assertFileContainsWord($this->testFile, 'test');
    $this->assertFileContainsWord($this->testFile, 'testing');

    // This should fail - only a part of a word.
    try {
      $this->assertFileContainsWord($this->testFile, 'tes');
      $this->fail('Assertion should have failed for partial word');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('should contain "tes" word', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFileNotContainsWordSuccess(): void {
    file_put_contents($this->testFile, 'This is a test content');
    $this->assertFileNotContainsWord($this->testFile, 'nonexistent');
    // Part of a word, but not a complete word - should pass.
    $this->assertFileNotContainsWord($this->testFile, 'tes');
  }

  public function testAssertFileNotContainsWordFailure(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileNotContainsWord($this->testFile, 'test');
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
    $this->assertFileContainsString($this->testFile, '/\d+/');
    $this->assertFileNotContainsString($this->testFile, '/\d{4,}/');

    // Regular string content should also work.
    $this->assertFileContainsString($this->testFile, '123');
    $this->assertFileNotContainsString($this->testFile, '456');
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

  /**
   * Data provider for assertFilesExist tests.
   */
  public static function assertFilesExistDataProvider(): array {
    return [
      'single file success' => [['test1.txt'], [], TRUE, ''],
      'multiple files success' => [['test1.txt', 'test2.txt', 'test3.txt'], [], TRUE, ''],
      'files with different extensions success' => [['test.txt', 'data.json', 'config.yml'], [], TRUE, ''],
      'empty array success' => [[], [], TRUE, ''],
      'nonexistent file failure' => [['existing.txt', 'nonexistent.txt'], ['existing.txt'], FALSE, 'nonexistent.txt'],
    ];
  }

  /**
   * Test assertFilesExist method with data provider.
   */
  #[DataProvider('assertFilesExistDataProvider')]
  public function testAssertFilesExist(array $files, array $create_files, bool $should_pass, string $expected_error): void {
    // Create specified files.
    foreach ($create_files as $file) {
      file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . $file, 'content');
    }

    // If no specific files to create, create all files from the test case.
    if (empty($create_files) && $should_pass) {
      foreach ($files as $file) {
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . $file, 'test content');
      }
    }

    if ($should_pass) {
      $this->assertFilesExist($this->tmpDir, $files);
      // Add assertion to avoid risky tests for empty arrays.
      // @phpstan-ignore-next-line
      $this->assertTrue(TRUE);
    }
    else {
      try {
        $this->assertFilesExist($this->tmpDir, $files);
        $this->fail('Assertion should have failed');
      }
      catch (AssertionFailedError $assertionFailedError) {
        $this->assertStringContainsString($expected_error, $assertionFailedError->getMessage());
      }
    }
  }

  /**
   * Data provider for assertFilesDoNotExist tests.
   */
  public static function assertFilesDoNotExistDataProvider(): array {
    return [
      'single nonexistent file success' => [['nonexistent1.txt'], [], TRUE, ''],
      'multiple nonexistent files success' => [['nonexistent1.txt', 'nonexistent2.txt', 'nonexistent3.txt'], [], TRUE, ''],
      'files with different extensions success' => [['missing.txt', 'absent.json', 'gone.yml'], [], TRUE, ''],
      'empty array success' => [[], [], TRUE, ''],
      'existing file failure' => [['existing.txt'], ['existing.txt'], FALSE, 'existing.txt'],
    ];
  }

  /**
   * Test assertFilesDoNotExist method with data provider.
   */
  #[DataProvider('assertFilesDoNotExistDataProvider')]
  public function testAssertFilesDoNotExist(array $files, array $create_files, bool $should_pass, string $expected_error): void {
    // Create specified files.
    foreach ($create_files as $file) {
      file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . $file, 'content');
    }

    if ($should_pass) {
      $this->assertFilesDoNotExist($this->tmpDir, $files);
      // Add assertion to avoid risky tests for empty arrays.
      // @phpstan-ignore-next-line
      $this->assertTrue(TRUE);
    }
    else {
      try {
        $this->assertFilesDoNotExist($this->tmpDir, $files);
        $this->fail('Assertion should have failed');
      }
      catch (AssertionFailedError $assertionFailedError) {
        $this->assertStringContainsString($expected_error, $assertionFailedError->getMessage());
      }
    }
  }

  /**
   * Data provider for assertFilesWildcardExists tests.
   */
  public static function assertFilesWildcardExistsDataProvider(): array {
    return [
      'single pattern string success' => ['*.txt', ['test.txt'], TRUE, ''],
      'single pattern array success' => [['*.txt'], ['test.txt'], TRUE, ''],
      'multiple patterns success' => [['*.txt', '*.json'], ['test.txt', 'data.json'], TRUE, ''],
      'directory pattern success' => ['subdir/*.txt', ['subdir/file.txt'], TRUE, ''],
      'prefix pattern success' => ['test_*.txt', ['test_file.txt'], TRUE, ''],
      'no matches failure' => ['*.nonexistent', [], FALSE, 'No files found matching wildcard pattern'],
      'empty patterns exception' => [[], [], 'exception', 'Empty patterns'],
    ];
  }

  /**
   * Test assertFilesWildcardExists method with data provider.
   */
  #[DataProvider('assertFilesWildcardExistsDataProvider')]
  public function testAssertFilesWildcardExists(string|array $patterns, array $create_files, bool|string $should_pass, string $expected_error): void {
    // Create files.
    foreach ($create_files as $file) {
      $file_path = $this->tmpDir . DIRECTORY_SEPARATOR . $file;
      $dir = dirname($file_path);
      if (!is_dir($dir)) {
        mkdir($dir, 0777, TRUE);
      }
      file_put_contents($file_path, 'content');
    }

    // Convert patterns to full paths.
    $full_patterns = is_array($patterns) ?
      array_map(fn($p): string => $this->tmpDir . DIRECTORY_SEPARATOR . $p, $patterns) :
      $this->tmpDir . DIRECTORY_SEPARATOR . $patterns;

    if ($should_pass === 'exception') {
      try {
        $this->assertFilesWildcardExists($patterns);
        $this->fail('Should throw InvalidArgumentException');
      }
      catch (\InvalidArgumentException $invalidArgumentException) {
        $this->assertStringContainsString($expected_error, $invalidArgumentException->getMessage());
      }
    }
    elseif ($should_pass) {
      $this->assertFilesWildcardExists($full_patterns);
    }
    else {
      try {
        $this->assertFilesWildcardExists($full_patterns);
        $this->fail('Assertion should have failed');
      }
      catch (AssertionFailedError $assertionFailedError) {
        $this->assertStringContainsString($expected_error, $assertionFailedError->getMessage());
      }
    }
  }

  /**
   * Data provider for assertFilesWildcardDoNotExist tests.
   */
  public static function assertFilesWildcardDoNotExistDataProvider(): array {
    return [
      'single pattern string success' => ['*.nonexistent', [], TRUE, ''],
      'single pattern array success' => [['*.nonexistent'], [], TRUE, ''],
      'multiple patterns success' => [['*.nonexistent', '*.missing'], [], TRUE, ''],
      'directory pattern success' => ['nonexistent_dir/*.txt', [], TRUE, ''],
      'matching files failure' => ['*.txt', ['test.txt'], FALSE, 'Found 1 file(s) matching wildcard pattern that should not exist'],
      'empty patterns exception' => [[], [], 'exception', 'Empty patterns'],
    ];
  }

  /**
   * Test assertFilesWildcardDoNotExist method with data provider.
   */
  #[DataProvider('assertFilesWildcardDoNotExistDataProvider')]
  public function testAssertFilesWildcardDoNotExist(string|array $patterns, array $create_files, bool|string $should_pass, string $expected_error): void {
    // Create files.
    foreach ($create_files as $file) {
      file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . $file, 'content');
    }

    // Convert patterns to full paths.
    $full_patterns = is_array($patterns) ?
      array_map(fn($p): string => $this->tmpDir . DIRECTORY_SEPARATOR . $p, $patterns) :
      $this->tmpDir . DIRECTORY_SEPARATOR . $patterns;

    if ($should_pass === 'exception') {
      try {
        $this->assertFilesWildcardDoNotExist($patterns);
        $this->fail('Should throw InvalidArgumentException');
      }
      catch (\InvalidArgumentException $invalidArgumentException) {
        $this->assertStringContainsString($expected_error, $invalidArgumentException->getMessage());
      }
    }
    elseif ($should_pass) {
      $this->assertFilesWildcardDoNotExist($full_patterns);
    }
    else {
      try {
        $this->assertFilesWildcardDoNotExist($full_patterns);
        $this->fail('Assertion should have failed');
      }
      catch (AssertionFailedError $assertionFailedError) {
        $this->assertStringContainsString($expected_error, $assertionFailedError->getMessage());
      }
    }
  }

  public function testAssertFileContainsWordWithSlashes(): void {
    file_put_contents($this->testFile, 'This contains path/to/file and other/different content');

    // Test that needles containing forward slashes work correctly.
    $this->assertFileContainsWord($this->testFile, 'path/to/file');

    // Test that non-existent paths with slashes don't match.
    $this->assertFileNotContainsWord($this->testFile, 'path/to/nonexistent');
  }

  public function testAssertFilesExistWithCustomMessage(): void {
    // Create test files.
    file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . 'test1.txt', 'content');

    // Test successful assertion with custom message.
    $this->assertFilesExist($this->tmpDir, ['test1.txt'], 'Custom success message');

    // Test failed assertion with custom message.
    try {
      $this->assertFilesExist($this->tmpDir, ['nonexistent.txt'], 'Custom failure message');
      $this->fail('Assertion should have failed');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom failure message', $assertionFailedError->getMessage());
    }
  }

  public function testAssertFilesWildcardExistsWithCustomMessage(): void {
    // Create test files.
    file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . 'test.txt', 'content');

    // Test successful assertion with custom message.
    $pattern = $this->tmpDir . DIRECTORY_SEPARATOR . '*.txt';
    $this->assertFilesWildcardExists($pattern, 'Custom success message');

    // Test failed assertion with custom message.
    $nonexistent_pattern = $this->tmpDir . DIRECTORY_SEPARATOR . '*.nonexistent';
    try {
      $this->assertFilesWildcardExists($nonexistent_pattern, 'Custom failure message');
      $this->fail('Assertion should have failed');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom failure message', $assertionFailedError->getMessage());
    }
  }

}
