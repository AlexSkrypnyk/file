<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Testing\FileAssertionsTrait;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileAssertionsTrait::class)]
final class FileAssertionsTraitTest extends TestCase {

  use FileAssertionsTrait;

  protected string $testTmpDir;
  protected string $testFile;

  #[\Override]
  protected function setUp(): void {
    $this->testTmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_assertions_test_', TRUE);
    mkdir($this->testTmpDir, 0777, TRUE);
    $this->testFile = $this->testTmpDir . DIRECTORY_SEPARATOR . 'test.txt';
  }

  #[\Override]
  protected function tearDown(): void {
    if (is_dir($this->testTmpDir)) {
      File::remove($this->testTmpDir);
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
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('should contain', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertFileContainsStringCustomMessage(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileContainsString($this->testFile, 'nonexistent', 'Custom message for nonexistent string');
      $this->fail('Assertion should have failed for nonexistent string');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for nonexistent string', $assertion_failed_error->getMessage());
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
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('should not contain', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertFileNotContainsStringCustomMessage(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileNotContainsString($this->testFile, 'test', 'Custom message for existing string');
      $this->fail('Assertion should have failed for existing string with custom message');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for existing string', $assertion_failed_error->getMessage());
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
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('should contain', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('word', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertFileContainsWordBoundaries(): void {
    file_put_contents($this->testFile, 'Testing test tests tester testing');

    $this->assertFileContainsWord($this->testFile, 'test');
    $this->assertFileContainsWord($this->testFile, 'testing');

    try {
      $this->assertFileContainsWord($this->testFile, 'tes');
      $this->fail('Assertion should have failed for partial word');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('should contain "tes" word', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertFileNotContainsWordSuccess(): void {
    file_put_contents($this->testFile, 'This is a test content');
    $this->assertFileNotContainsWord($this->testFile, 'nonexistent');
    $this->assertFileNotContainsWord($this->testFile, 'tes');
  }

  public function testAssertFileNotContainsWordFailure(): void {
    file_put_contents($this->testFile, 'This is a test content');

    try {
      $this->assertFileNotContainsWord($this->testFile, 'test');
      $this->fail('Assertion should have failed for existing word');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('should not contain', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('word', $assertion_failed_error->getMessage());
    }
  }

  public function testRegexPatterns(): void {
    file_put_contents($this->testFile, 'Testing with numbers: 123 and words.');

    $this->assertFileContainsString($this->testFile, '/\d+/');
    $this->assertFileNotContainsString($this->testFile, '/\d{4,}/');

    $this->assertFileContainsString($this->testFile, '123');
    $this->assertFileNotContainsString($this->testFile, '456');
  }

  public function testAssertFileEqualsFileSuccess(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    $content = 'This is a test content';
    file_put_contents($file1, $content);
    file_put_contents($file2, $content);

    // Files with same content but different permissions should be equal.
    chmod($file1, 0644);
    chmod($file2, 0600);

    $this->assertFileEqualsFile($file1, $file2);
  }

  public function testAssertFileEqualsFileFailure(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is content for file 1');
    file_put_contents($file2, 'This is different content for file 2');

    try {
      $this->assertFileEqualsFile($file1, $file2);
      $this->fail('Assertion should have failed for different content');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('match', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertFileEqualsFileNonexistentFiles(): void {
    $file = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file.txt';
    $nonexistent = $this->testTmpDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';

    file_put_contents($file, 'Some content');

    try {
      $this->assertFileEqualsFile($nonexistent, $file);
      $this->fail('Assertion should have failed for nonexistent expected file');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('does not exist', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertFileEqualsFile($file, $nonexistent);
      $this->fail('Assertion should have failed for nonexistent actual file');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('does not exist', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertFileNotEqualsFileSuccess(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'Content for file 1');
    file_put_contents($file2, 'Different content for file 2');

    $this->assertFileNotEqualsFile($file1, $file2);
  }

  public function testAssertFileNotEqualsFileFailure(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    $content = 'Identical content in both files';
    file_put_contents($file1, $content);
    file_put_contents($file2, $content);

    try {
      $this->assertFileNotEqualsFile($file1, $file2);
      $this->fail('Assertion should have failed for identical content');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('identical contents', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertFileNotEqualsFileNonexistentFiles(): void {
    $file = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file.txt';
    $nonexistent = $this->testTmpDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';

    file_put_contents($file, 'Some content');

    try {
      $this->assertFileNotEqualsFile($nonexistent, $file);
      $this->fail('Assertion should have failed for nonexistent expected file');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('does not exist', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertFileNotEqualsFile($file, $nonexistent);
      $this->fail('Assertion should have failed for nonexistent actual file');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('does not exist', $assertion_failed_error->getMessage());
    }
  }

  public function testFileComparisonCustomMessages(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'Content for file 1');
    file_put_contents($file2, 'Different content for file 2');

    try {
      $this->assertFileEqualsFile($file1, $file2, 'Custom message for different files');
      $this->fail('Assertion should have failed with custom message');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for different files', $assertion_failed_error->getMessage());
    }

    file_put_contents($file2, 'Content for file 1');

    try {
      $this->assertFileNotEqualsFile($file1, $file2, 'Custom message for identical files');
      $this->fail('Assertion should have failed with custom message');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for identical files', $assertion_failed_error->getMessage());
    }
  }

  #[DataProvider('dataProviderAssertFilesExist')]
  public function testAssertFilesExist(array $files, array $create_files, bool $should_pass, string $expected_error): void {
    foreach ($create_files as $file) {
      file_put_contents($this->testTmpDir . DIRECTORY_SEPARATOR . $file, 'content');
    }

    if (empty($create_files) && $should_pass) {
      foreach ($files as $file) {
        file_put_contents($this->testTmpDir . DIRECTORY_SEPARATOR . $file, 'test content');
      }
    }

    if ($should_pass) {
      $this->assertFilesExist($this->testTmpDir, $files);
      // Register an assertion so an empty array does not mark the test risky.
      $this->addToAssertionCount(1);
    }
    else {
      try {
        $this->assertFilesExist($this->testTmpDir, $files);
        $this->fail('Assertion should have failed');
      }
      catch (AssertionFailedError $assertion_failed_error) {
        $this->assertStringContainsString($expected_error, $assertion_failed_error->getMessage());
      }
    }
  }

  public static function dataProviderAssertFilesExist(): \Iterator {
    yield 'single file success' => [['test1.txt'], [], TRUE, ''];
    yield 'multiple files success' => [['test1.txt', 'test2.txt', 'test3.txt'], [], TRUE, ''];
    yield 'files with different extensions success' => [['test.txt', 'data.json', 'config.yml'], [], TRUE, ''];
    yield 'empty array success' => [[], [], TRUE, ''];
    yield 'nonexistent file failure' => [['existing.txt', 'nonexistent.txt'], ['existing.txt'], FALSE, 'nonexistent.txt'];
  }

  #[DataProvider('dataProviderAssertFilesDoNotExist')]
  public function testAssertFilesDoNotExist(array $files, array $create_files, bool $should_pass, string $expected_error): void {
    foreach ($create_files as $file) {
      file_put_contents($this->testTmpDir . DIRECTORY_SEPARATOR . $file, 'content');
    }

    if ($should_pass) {
      $this->assertFilesDoNotExist($this->testTmpDir, $files);
      // Register an assertion so an empty array does not mark the test risky.
      $this->addToAssertionCount(1);
    }
    else {
      try {
        $this->assertFilesDoNotExist($this->testTmpDir, $files);
        $this->fail('Assertion should have failed');
      }
      catch (AssertionFailedError $assertion_failed_error) {
        $this->assertStringContainsString($expected_error, $assertion_failed_error->getMessage());
      }
    }
  }

  public static function dataProviderAssertFilesDoNotExist(): \Iterator {
    yield 'single nonexistent file success' => [['nonexistent1.txt'], [], TRUE, ''];
    yield 'multiple nonexistent files success' => [['nonexistent1.txt', 'nonexistent2.txt', 'nonexistent3.txt'], [], TRUE, ''];
    yield 'files with different extensions success' => [['missing.txt', 'absent.json', 'gone.yml'], [], TRUE, ''];
    yield 'empty array success' => [[], [], TRUE, ''];
    yield 'existing file failure' => [['existing.txt'], ['existing.txt'], FALSE, 'existing.txt'];
  }

  #[DataProvider('dataProviderAssertFilesWildcardExists')]
  public function testAssertFilesWildcardExists(string|array $patterns, array $create_files, bool|string $should_pass, string $expected_error): void {
    foreach ($create_files as $file) {
      $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . $file;
      $dir = dirname($file_path);
      if (!is_dir($dir)) {
        mkdir($dir, 0777, TRUE);
      }
      file_put_contents($file_path, 'content');
    }

    $full_patterns = is_array($patterns) ?
      array_map(fn($p): string => $this->testTmpDir . DIRECTORY_SEPARATOR . $p, $patterns) :
      $this->testTmpDir . DIRECTORY_SEPARATOR . $patterns;

    if ($should_pass === 'exception') {
      $this->expectException(\InvalidArgumentException::class);
      $this->expectExceptionMessage($expected_error);
      $this->assertFilesWildcardExists($patterns);
    }
    elseif ($should_pass) {
      $this->assertFilesWildcardExists($full_patterns);
    }
    else {
      try {
        $this->assertFilesWildcardExists($full_patterns);
        $this->fail('Assertion should have failed');
      }
      catch (AssertionFailedError $assertion_failed_error) {
        $this->assertStringContainsString($expected_error, $assertion_failed_error->getMessage());
      }
    }
  }

  public static function dataProviderAssertFilesWildcardExists(): \Iterator {
    yield 'single pattern string success' => ['*.txt', ['test.txt'], TRUE, ''];
    yield 'single pattern array success' => [['*.txt'], ['test.txt'], TRUE, ''];
    yield 'multiple patterns success' => [['*.txt', '*.json'], ['test.txt', 'data.json'], TRUE, ''];
    yield 'directory pattern success' => ['subdir/*.txt', ['subdir/file.txt'], TRUE, ''];
    yield 'prefix pattern success' => ['test_*.txt', ['test_file.txt'], TRUE, ''];
    yield 'no matches failure' => ['*.nonexistent', [], FALSE, 'No files found matching wildcard pattern'];
    yield 'empty patterns exception' => [[], [], 'exception', 'Empty patterns'];
  }

  #[DataProvider('dataProviderAssertFilesWildcardDoNotExist')]
  public function testAssertFilesWildcardDoNotExist(string|array $patterns, array $create_files, bool|string $should_pass, string $expected_error): void {
    foreach ($create_files as $file) {
      file_put_contents($this->testTmpDir . DIRECTORY_SEPARATOR . $file, 'content');
    }

    $full_patterns = is_array($patterns) ?
      array_map(fn($p): string => $this->testTmpDir . DIRECTORY_SEPARATOR . $p, $patterns) :
      $this->testTmpDir . DIRECTORY_SEPARATOR . $patterns;

    if ($should_pass === 'exception') {
      $this->expectException(\InvalidArgumentException::class);
      $this->expectExceptionMessage($expected_error);
      $this->assertFilesWildcardDoNotExist($patterns);
    }
    elseif ($should_pass) {
      $this->assertFilesWildcardDoNotExist($full_patterns);
    }
    else {
      try {
        $this->assertFilesWildcardDoNotExist($full_patterns);
        $this->fail('Assertion should have failed');
      }
      catch (AssertionFailedError $assertion_failed_error) {
        $this->assertStringContainsString($expected_error, $assertion_failed_error->getMessage());
      }
    }
  }

  public static function dataProviderAssertFilesWildcardDoNotExist(): \Iterator {
    yield 'single pattern string success' => ['*.nonexistent', [], TRUE, ''];
    yield 'single pattern array success' => [['*.nonexistent'], [], TRUE, ''];
    yield 'multiple patterns success' => [['*.nonexistent', '*.missing'], [], TRUE, ''];
    yield 'directory pattern success' => ['nonexistent_dir/*.txt', [], TRUE, ''];
    yield 'matching files failure' => ['*.txt', ['test.txt'], FALSE, 'Found 1 file(s) matching wildcard pattern that should not exist'];
    yield 'empty patterns exception' => [[], [], 'exception', 'Empty patterns'];
  }

  public function testAssertFileContainsWordWithSlashes(): void {
    file_put_contents($this->testFile, 'This contains path/to/file and other/different content');

    $this->assertFileContainsWord($this->testFile, 'path/to/file');

    $this->assertFileNotContainsWord($this->testFile, 'path/to/nonexistent');
  }

  public function testAssertFilesExistWithCustomMessage(): void {
    file_put_contents($this->testTmpDir . DIRECTORY_SEPARATOR . 'test1.txt', 'content');

    $this->assertFilesExist($this->testTmpDir, ['test1.txt'], 'Custom success message');

    try {
      $this->assertFilesExist($this->testTmpDir, ['nonexistent.txt'], 'Custom failure message');
      $this->fail('Assertion should have failed');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom failure message', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertFilesWildcardExistsWithCustomMessage(): void {
    file_put_contents($this->testTmpDir . DIRECTORY_SEPARATOR . 'test.txt', 'content');

    $pattern = $this->testTmpDir . DIRECTORY_SEPARATOR . '*.txt';
    $this->assertFilesWildcardExists($pattern, 'Custom success message');

    $nonexistent_pattern = $this->testTmpDir . DIRECTORY_SEPARATOR . '*.nonexistent';
    try {
      $this->assertFilesWildcardExists($nonexistent_pattern, 'Custom failure message');
      $this->fail('Assertion should have failed');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom failure message', $assertion_failed_error->getMessage());
    }
  }

}
