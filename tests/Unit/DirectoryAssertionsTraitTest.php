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

    $this->assertDirectoryContainsString('test', $this->tmpDir);
    $this->addToAssertionCount(1);

    $excluded = ['file1.txt'];
    $this->assertDirectoryContainsString('another', $this->tmpDir, $excluded);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryContainsStringNegative(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    try {
      $this->assertDirectoryContainsString('nonexistent', $this->tmpDir);
      $this->fail('Assertion should have failed for nonexistent string');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Directory should contain "nonexistent"', $assertionFailedError->getMessage());
    }

    try {
      $this->assertDirectoryContainsString('nonexistent', $this->tmpDir, [], 'Custom message for nonexistent string');
      $this->fail('Assertion should have failed for nonexistent string');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom message for nonexistent string', $assertionFailedError->getMessage());
    }
  }

  public function testAssertDirectoryNotContainsStringPositive(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    $this->assertDirectoryNotContainsString('nonexistent', $this->tmpDir);
    $this->addToAssertionCount(1);

    $this->assertDirectoryNotContainsString('test', $this->tmpDir, ['file1.txt']);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryNotContainsStringNegative(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    try {
      $this->assertDirectoryNotContainsString('test', $this->tmpDir);
      $this->fail('Assertion should have failed for existing string');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Directory should not contain "test"', $assertionFailedError->getMessage());
      $this->assertStringContainsString('file1.txt', $assertionFailedError->getMessage());
    }

    try {
      $this->assertDirectoryNotContainsString('test', $this->tmpDir, [], 'Custom message for existing string');
      $this->fail('Assertion should have failed for existing string with custom message');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom message for existing string', $assertionFailedError->getMessage());
    }
  }

  public function testAssertDirectoryContainsWordPositive(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content with testing');

    $this->assertDirectoryContainsWord('test', $this->tmpDir);
    $this->addToAssertionCount(1);

    $excluded = ['file2.txt'];
    $this->assertDirectoryContainsWord('test', $this->tmpDir, $excluded);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryContainsWordNegative(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content with testing');

    try {
      $this->assertDirectoryContainsWord('nonexistent', $this->tmpDir);
      $this->fail('Assertion should have failed for nonexistent word');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Directory should contain "nonexistent" word', $assertionFailedError->getMessage());
    }

    try {
      $this->assertDirectoryContainsWord('nonexistent', $this->tmpDir, [], 'Custom message for nonexistent word');
      $this->fail('Assertion should have failed for nonexistent word with custom message');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom message for nonexistent word', $assertionFailedError->getMessage());
    }

    try {
      $this->assertDirectoryContainsWord('tes', $this->tmpDir);
      $this->fail('Assertion should have failed for partial word match');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Directory should contain "tes" word', $assertionFailedError->getMessage());
    }
  }

  public function testAssertDirectoryNotContainsWordPositive(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    $this->assertDirectoryNotContainsWord('nonexistent', $this->tmpDir);
    $this->addToAssertionCount(1);

    $this->assertDirectoryNotContainsWord('tes', $this->tmpDir);
    $this->addToAssertionCount(1);

    $this->assertDirectoryNotContainsWord('test', $this->tmpDir, ['file1.txt']);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoryNotContainsWordNegative(): void {
    $file1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'This is a test content');
    file_put_contents($file2, 'This is another content');

    try {
      $this->assertDirectoryNotContainsWord('test', $this->tmpDir);
      $this->fail('Assertion should have failed for existing word');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Directory should not contain "test" word', $assertionFailedError->getMessage());
      $this->assertStringContainsString('file1.txt', $assertionFailedError->getMessage());
    }

    try {
      $this->assertDirectoryNotContainsWord('test', $this->tmpDir, [], 'Custom message for existing word');
      $this->fail('Assertion should have failed for existing word with custom message');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom message for existing word', $assertionFailedError->getMessage());
    }

    try {
      $this->assertDirectoryNotContainsWord('another', $this->tmpDir, ['file1.txt']);
      $this->fail('Assertion should have failed for word in non-excluded file');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('file2.txt', $assertionFailedError->getMessage());
      $this->assertStringNotContainsString('file1.txt', $assertionFailedError->getMessage());
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
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Files that differ in content', $assertionFailedError->getMessage());
      $this->assertStringContainsString('file1.txt', $assertionFailedError->getMessage());
    }

    try {
      $this->assertDirectoryEqualsDirectory($dir1, $dir2, 'Custom message for missing files');
      $this->fail('Assertion should have failed for missing files');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Custom message for missing files', $assertionFailedError->getMessage());
      $this->assertStringContainsString('Files absent in', $assertionFailedError->getMessage());
      $this->assertStringContainsString('file2.txt', $assertionFailedError->getMessage());
      $this->assertStringContainsString('file3.txt', $assertionFailedError->getMessage());
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
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Files that differ in content', $assertionFailedError->getMessage());
      $this->assertStringContainsString('file1.txt', $assertionFailedError->getMessage());
    }
  }

  public function testAssertDirectoryEqualsPatchedBaselineWithNonexistentBaseline(): void {
    $nonexistentDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'nonexistent';

    try {
      $this->assertDirectoryEqualsPatchedBaseline($this->actualDir, $nonexistentDir, $this->diffDir);
      $this->fail('Assertion should have failed for nonexistent baseline directory');
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('The baseline directory does not exist', $assertionFailedError->getMessage());
      $this->assertStringContainsString($nonexistentDir, $assertionFailedError->getMessage());
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
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Failed to apply patch', $assertionFailedError->getMessage());
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
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Failed to apply patch', $assertionFailedError->getMessage());
      $this->assertStringContainsString('Hunk mismatch', $assertionFailedError->getMessage());
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
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertStringContainsString('Failed to apply patch', $assertionFailedError->getMessage());
      $this->assertStringContainsString('Unexpected EOF', $assertionFailedError->getMessage());
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

    $expectedIgnoreContentPath = $this->expectedDir . DIRECTORY_SEPARATOR . Index::IGNORECONTENT;
    $this->assertFileExists($expectedIgnoreContentPath);
    $content = file_get_contents($expectedIgnoreContentPath);
    $this->assertIsString($content);
    $this->assertEquals("*.ignored\n*.log", $content);
  }

}
