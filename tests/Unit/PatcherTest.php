<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\ExtendedSplFileInfo;
use AlexSkrypnyk\File\Internal\Patcher;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Patcher::class)]
class PatcherTest extends UnitTestCase {

  #[DataProvider('dataProviderIsPatchFile')]
  public function testIsPatchFile(string $file_path, bool $expected): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . $file_path;
    $dir = dirname($file_path);
    if (!file_exists($dir)) {
      mkdir($dir, 0777, TRUE);
    }

    if (str_contains($file_path, 'directory')) {
      mkdir($file_path, 0777, TRUE);
    }
    elseif (str_contains($file_path, 'symlink')) {
      touch($file_path . '_target');
      symlink($file_path . '_target', $file_path);
    }
    elseif (str_contains($file_path, 'with_content')) {
      file_put_contents($file_path, "Some content\n@@ -1,1 +1,1 @@\n");
    }
    else {
      file_put_contents($file_path, "Some content without patch markers");
    }

    $result = Patcher::isPatchFile($file_path);
    $this->assertEquals($expected, $result);
  }

  public static function dataProviderIsPatchFile(): array {
    return [
      'non_existent_file' => ['non_existent.patch', FALSE],
      'directory' => ['directory', FALSE],
      'symlink' => ['symlink.patch', FALSE],
      'file_with_content' => ['with_content.patch', TRUE],
      'file_without_patch_content' => ['without_patch_content.txt', FALSE],
    ];
  }

  public function testAddPatchFile(): void {
    $patch_content = "@@ -1,1 +1,1 @@\n-old line\n+new line\n";
    $patch_file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.patch';
    file_put_contents($patch_file_path, $patch_content);

    $file_info = new ExtendedSplFileInfo(
      $patch_file_path,
      '',
      ''
    );

    $patcher = new Patcher(static::$sut, static::$sut);
    $result = $patcher->addPatchFile($file_info);

    $this->assertInstanceOf(Patcher::class, $result);
  }

  public function testAddPatchFileInvalid(): void {
    $content = "Not a patch file";
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'not_a_patch.txt';
    file_put_contents($file_path, $content);

    $file_info = new ExtendedSplFileInfo(
      $file_path,
      '',
      ''
    );

    $patcher = new Patcher(static::$sut, static::$sut);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage(sprintf('Invalid patch file: %s', $file_path));

    $patcher->addPatchFile($file_info);
  }

  public function testAddDiff(): void {
    $patcher = new Patcher(static::$sut, static::$sut);

    $diff_string = "@@ -1,1 +1,1 @@\n-old line\n+new line\n";
    $result1 = $patcher->addDiff($diff_string, 'test.txt');
    $this->assertInstanceOf(Patcher::class, $result1);

    $diff_array = ["@@ -1,1 +1,1 @@", "-old line", "+new line"];
    $result2 = $patcher->addDiff($diff_array, 'test2.txt');
    $this->assertInstanceOf(Patcher::class, $result2);
  }

  public function testSplitLines(): void {
    $content = "line1\nline2\r\nline3\rline4";

    $result = self::callProtectedMethod(Patcher::class, 'splitLines', [$content]);

    $expected = ['line1', 'line2', 'line3', 'line4'];
    $this->assertEquals($expected, $result);
  }

  public function testSplitLinesEdgeCases(): void {
    $result1 = self::callProtectedMethod(Patcher::class, 'splitLines', ['']);
    $this->assertEquals([''], $result1);

    $result2 = self::callProtectedMethod(Patcher::class, 'splitLines', ['single line']);
    $this->assertEquals(['single line'], $result2);

    $result3 = self::callProtectedMethod(Patcher::class, 'splitLines', ["\n\n\n"]);
    $this->assertEquals(['', '', '', ''], $result3);
  }

  public function testFilePatch(): void {
    $baseline_dir = static::$sut . DIRECTORY_SEPARATOR . 'baseline';
    $diff_dir = static::$sut . DIRECTORY_SEPARATOR . 'diff';
    $dest_dir = static::$sut . DIRECTORY_SEPARATOR . 'dest';

    mkdir($baseline_dir, 0777, TRUE);
    mkdir($diff_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $baseline_file = $baseline_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($baseline_file, "line1\nline2\nline3\n");

    $diff_content = "@@ -1,3 +1,3 @@\n line1\n-line2\n+new line 2\n line3\n";
    $diff_file = $diff_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($diff_file, $diff_content);

    File::patch($baseline_dir, $diff_dir, $dest_dir);

    $this->assertFileExists($dest_dir . DIRECTORY_SEPARATOR . 'test.txt');
    $this->assertEquals("line1\nnew line 2\nline3\n", file_get_contents($dest_dir . DIRECTORY_SEPARATOR . 'test.txt'));
  }

  public function testFindHunk(): void {
    $lines = [
      "@@ -1,3 +1,3 @@",
      " line1",
      "-line2",
      "+new line 2",
      " line3",
    ];
    reset($lines);

    $result = self::callProtectedMethod(Patcher::class, 'findHunk', [&$lines]);

    $expected = [
      'src_idx' => 1,
      'src_size' => 3,
      'dst_idx' => 1,
      'dst_size' => 3,
    ];
    $this->assertEquals($expected, $result);
    $this->assertEquals(" line1", current($lines));
  }

  public function testFindHunkNull(): void {
    $lines = ["Not a hunk header"];
    reset($lines);

    $result = self::callProtectedMethod(Patcher::class, 'findHunk', [&$lines]);

    $this->assertNull($result);
  }

  public function testFindHunkUnexpectedEof(): void {
    $lines = ["@@ -1,3 +1,3 @@"];
    reset($lines);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Unexpected EOF.');

    self::callProtectedMethod(Patcher::class, 'findHunk', [&$lines]);
  }

  public function testApplyHunk(): void {
    $source_dir = static::$sut . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = static::$sut . DIRECTORY_SEPARATOR . 'dest';
    mkdir($source_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $source_file = $source_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($source_file, "line1\nline2\nline3\n");

    $diff = [
      " line1",
      "-line2",
      "+new line 2",
      " line3",
    ];
    reset($diff);

    $info = [
      'src_idx' => 1,
      'src_size' => 3,
      'dst_idx' => 1,
      'dst_size' => 3,
    ];

    $patcher = new Patcher($source_dir, $dest_dir);
    $dst_file = $dest_dir . DIRECTORY_SEPARATOR . 'test.txt';

    self::callProtectedMethod($patcher, 'applyHunk', [
      &$diff,
      $source_file,
      $dst_file,
      $info,
    ]);

    self::callProtectedMethod($patcher, 'updateDestinations', []);

    $this->assertFileExists($dst_file);
    $this->assertEquals("line1\nnew line 2\nline3\n", file_get_contents($dst_file));
  }

  public function testApplyHunkNoNewline(): void {
    $source_dir = static::$sut . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = static::$sut . DIRECTORY_SEPARATOR . 'dest';
    mkdir($source_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $source_file = $source_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($source_file, "line1\nline2\nline3");

    $diff = [
      " line1",
      "-line2",
      "+new line 2",
      " line3",
      "\\ No newline at end of file",
    ];
    reset($diff);

    $info = [
      'src_idx' => 1,
      'src_size' => 3,
      'dst_idx' => 1,
      'dst_size' => 3,
    ];

    $patcher = new Patcher($source_dir, $dest_dir);
    $dst_file = $dest_dir . DIRECTORY_SEPARATOR . 'test.txt';

    self::callProtectedMethod($patcher, 'applyHunk', [
      &$diff,
      $source_file,
      $dst_file,
      $info,
    ]);

    self::callProtectedMethod($patcher, 'updateDestinations', []);

    $this->assertFileExists($dst_file);
    $this->assertEquals("line1\nnew line 2\nline3", file_get_contents($dst_file));
  }

  public function testApplyHunkSourceMismatch(): void {
    $source_dir = static::$sut . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = static::$sut . DIRECTORY_SEPARATOR . 'dest';
    mkdir($source_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $source_file = $source_dir . DIRECTORY_SEPARATOR . 'test.txt';
    $content = "different line1\ndifferent line2\ndifferent line3\n";
    file_put_contents($source_file, $content);

    $diff = [
      " line1",
      "-line2",
      "+new line 2",
      " line3",
    ];
    reset($diff);

    $info = [
      'src_idx' => 1,
      'src_size' => 3,
      'dst_idx' => 1,
      'dst_size' => 3,
    ];

    $patcher = new Patcher($source_dir, $dest_dir);
    $dst_file = $dest_dir . DIRECTORY_SEPARATOR . 'test.txt';

    $this->expectException(\Exception::class);
    $this->expectExceptionMessageMatches('/Source file verification failed/');

    self::callProtectedMethod($patcher, 'applyHunk', [
      &$diff,
      $source_file,
      $dst_file,
      $info,
    ]);
  }

  public function testApplyHunkMismatch(): void {
    $source_dir = static::$sut . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = static::$sut . DIRECTORY_SEPARATOR . 'dest';
    mkdir($source_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $source_file = $source_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($source_file, "line1\nline2\nline3\n");

    $diff = [
      " line1",
      "-line2",
    ];
    reset($diff);

    $info = [
      'src_idx' => 1,
      'src_size' => 3,
      'dst_idx' => 1,
      'dst_size' => 3,
    ];

    $patcher = new Patcher($source_dir, $dest_dir);
    $dst_file = $dest_dir . DIRECTORY_SEPARATOR . 'test.txt';

    $this->expectException(\Exception::class);
    $this->expectExceptionMessageMatches('/Hunk mismatch/');

    self::callProtectedMethod($patcher, 'applyHunk', [
      &$diff,
      $source_file,
      $dst_file,
      $info,
    ]);
  }

  public function testUpdateDestinations(): void {
    $patcher = new Patcher(static::$sut, static::$sut);

    $dest_file1 = static::$sut . DIRECTORY_SEPARATOR . 'file1.txt';
    $dest_file2 = static::$sut . DIRECTORY_SEPARATOR . 'file2.txt';

    self::setProtectedValue($patcher, 'dstLines', [
      $dest_file1 => ['line1', 'line2'],
      $dest_file2 => ['line3', 'line4'],
    ]);

    $result = self::callProtectedMethod($patcher, 'updateDestinations', []);

    $this->assertEquals(2, $result);
    $this->assertFileExists($dest_file1);
    $this->assertFileExists($dest_file2);
    $this->assertEquals("line1\nline2", file_get_contents($dest_file1));
    $this->assertEquals("line3\nline4", file_get_contents($dest_file2));
  }

}
