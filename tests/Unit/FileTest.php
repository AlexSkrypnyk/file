<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(File::class)]
#[CoversMethod(File::class, 'cwd')]
#[CoversMethod(File::class, 'realpath')]
#[CoversMethod(File::class, 'mkdir')]
#[CoversMethod(File::class, 'dir')]
#[CoversMethod(File::class, 'exists')]
#[CoversMethod(File::class, 'rmdir')]
#[CoversMethod(File::class, 'rmdirEmpty')]
#[CoversMethod(File::class, 'findMatchingPath')]
#[CoversMethod(File::class, 'copyIfExists')]
#[CoversMethod(File::class, 'scandirRecursive')]
#[CoversMethod(File::class, 'replaceContent')]
#[CoversMethod(File::class, 'removeToken')]
#[CoversMethod(File::class, 'diff')]
#[CoversMethod(File::class, 'tmpdir')]
#[CoversMethod(File::class, 'copy')]
class FileTest extends UnitTestCase {

  protected string $testTmpDir;

  #[\Override]
  protected function setUp(): void {
    $this->testTmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_test_', TRUE);
    mkdir($this->testTmpDir, 0777, TRUE);
  }

  #[\Override]
  protected function tearDown(): void {
    if (is_dir($this->testTmpDir)) {
      (new Filesystem())->remove($this->testTmpDir);
    }
  }

  #[DataProvider('dataProviderRealpath')]
  public function testRealpath(string $path, string $expected): void {
    $this->assertSame($expected, File::realpath($path));
  }

  public static function dataProviderRealpath(): array {
    $cwd = getcwd();

    if ($cwd === FALSE) {
      throw new \RuntimeException('Failed to determine current working directory.');
    }

    do {
      $tmp_dir = sprintf('%s%s%s%s', sys_get_temp_dir(), DIRECTORY_SEPARATOR, 'unit', mt_rand(100000, mt_getrandmax()));
    } while (!mkdir($tmp_dir, 0755, TRUE));

    $tmp_realpath = realpath($tmp_dir) ?: $tmp_dir;

    $symlink_target = $tmp_realpath . DIRECTORY_SEPARATOR . 'real_file.txt';
    $symlink_path = $tmp_realpath . DIRECTORY_SEPARATOR . 'symlink.txt';

    file_put_contents($symlink_target, 'test');
    if (!file_exists($symlink_path)) {
      symlink($symlink_target, $symlink_path);
    }

    return [
      // Absolute paths remain unchanged.
      ['/var/www/file.txt', '/var/www/file.txt'],

      // Relative path resolved from current working directory.
      ['file.txt', $cwd . DIRECTORY_SEPARATOR . 'file.txt'],

      // Parent directory resolution.
      ['../file.txt', dirname($cwd) . DIRECTORY_SEPARATOR . 'file.txt'],
      ['./file.txt', $cwd . DIRECTORY_SEPARATOR . 'file.txt'],

      // Temporary directory resolution.
      [$tmp_dir . DIRECTORY_SEPARATOR . 'file.txt', $tmp_realpath . DIRECTORY_SEPARATOR . 'file.txt'],

      // Symlink resolution.
      [$symlink_path, $symlink_target],
    ];
  }

  #[DataProvider('dataProviderAbsolutePath')]
  public function testAbsolute(string $file, ?string $base, string $expected): void {
    $this->assertSame($expected, File::absolute($file, $base));
  }

  public static function dataProviderAbsolutePath(): array {
    return [
      // Absolute path remains unchanged.
      ['/var/www/file.txt', NULL, '/var/www/file.txt'],
      ['/var/www/file.txt', '/base/path', '/var/www/file.txt'],

      // Relative path resolved from current working directory.
      ['file.txt', NULL, File::cwd() . DIRECTORY_SEPARATOR . 'file.txt'],

      // Relative path resolved from provided base path.
      ['file.txt', '/base/path', '/base/path/file.txt'],

      // Handling nested relative paths.
      ['../file.txt', '/base/path/subdir', '/base/path/file.txt'],
    ];
  }

  #[DataProvider('dataProviderDir')]
  public function testDir(string $directory, int $permissions, bool $expect_exception): void {
    if ($expect_exception) {
      $this->expectException(\RuntimeException::class);
    }

    $path = $this->testTmpDir . DIRECTORY_SEPARATOR . $directory;
    if (basename($path) === 'existing_file') {
      touch($path);
      $this->assertFileExists($path);
    }
    elseif (basename($path) === 'existing_dir') {
      mkdir($path, 0777, TRUE);
      $this->assertDirectoryExists($path);
    }
    else {
      $this->assertFileDoesNotExist($path);
    }

    $actual = File::dir($path);

    if (!$expect_exception) {
      $this->assertDirectoryExists($actual);
      $this->assertSame(realpath($actual), realpath($path));
      $this->assertFileExists($path);
    }
  }

  public static function dataProviderDir(): array {
    return [
      ['existing_dir', 0777, FALSE],
      ['non_existing_dir', 0777, TRUE],
      ['existing_file', 0777, TRUE],
    ];
  }

  #[DataProvider('dataProviderMkdir')]
  public function testMkdir(string $directory, int $permissions, bool $expect_exception): void {
    if ($expect_exception) {
      $this->expectException(\RuntimeException::class);
    }

    $path = $this->testTmpDir . DIRECTORY_SEPARATOR . $directory;
    if (basename($path) === 'existing_file') {
      touch($path);
      $this->assertFileExists($path);
    }
    elseif (basename($path) === 'existing_dir') {
      mkdir($path, 0777, TRUE);
      $this->assertDirectoryExists($path);
    }
    else {
      $this->assertFileDoesNotExist($path);
    }

    $actual = File::mkdir($path, $permissions);

    if (!$expect_exception) {
      $this->assertDirectoryExists($actual);
      $this->assertSame(realpath($actual), realpath($path));
      $this->assertTrue(File::exists($path));
    }
  }

  public static function dataProviderMkdir(): array {
    return [
      ['existing_dir', 0777, FALSE],
      ['non_existing_dir', 0777, FALSE],
      ['existing_file', 0777, TRUE],
    ];
  }

  #[DataProvider('dataProviderTmpDir')]
  public function testTmpDir(?string $directory, string $prefix): void {
    $path = File::tmpdir($directory, $prefix);

    $this->assertNotEmpty($prefix);
    $expected_base_dir = realpath($directory ?? sys_get_temp_dir());
    $this->assertNotFalse($expected_base_dir);
    $this->assertNotEmpty($expected_base_dir);
    $this->assertDirectoryExists($path);

    $path_basename = basename($path);
    $this->assertNotEmpty($path_basename);
    assert($prefix !== '');
    $this->assertStringStartsWith($prefix, $path_basename);

    $path_dirname = dirname($path);
    $this->assertNotEmpty($path_dirname);
    $this->assertStringStartsWith($expected_base_dir, $path_dirname);

    rmdir($path);
  }

  public static function dataProviderTmpDir(): array {
    $base = sys_get_temp_dir();

    return [
      'default dir, default prefix' => [NULL, 'tmp_'],
      'default dir, custom prefix' => [NULL, 'custom_'],
      'custom dir, default prefix' => [$base . DIRECTORY_SEPARATOR . 'custom_dir', 'tmp_'],
      'custom dir, custom prefix' => [$base . DIRECTORY_SEPARATOR . 'custom_dir', 'custom_'],
    ];
  }

  #[DataProvider('dataProviderFindMatchingPath')]
  public function testFindMatchingPath(array|string $paths, ?string $needle, ?string $expected_file): void {
    $result = File::findMatchingPath($paths, $needle);

    if ($expected_file) {
      $this->assertNotNull($result);
      $this->assertFileExists($result);
      $this->assertSame(realpath($expected_file), realpath($result));
    }
    else {
      $this->assertNull($result);
    }
  }

  public static function dataProviderFindMatchingPath(): array {
    $test_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_test_', TRUE);
    mkdir($test_dir, 0777, TRUE);

    $file1 = $test_dir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $test_dir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, "This is a test file containing needle.");
    file_put_contents($file2, "This is another file without the word.");

    return [
      'single path, no needle' => [$file1, NULL, $file1],
      'single path, with needle' => [$file1, 'needle', $file1],
      'single path, needle missing' => [$file2, 'needle', NULL],
      'glob pattern, first match' => [$test_dir . DIRECTORY_SEPARATOR . '*.txt', NULL, $file1],
      'glob pattern, matching content' => [$test_dir . DIRECTORY_SEPARATOR . '*.txt', 'needle', $file1],
      'glob pattern, no match' => [$test_dir . DIRECTORY_SEPARATOR . '*.md', NULL, NULL],
    ];
  }

  public function testRmdirAndRmdirEmpty(): void {
    $dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'test_dir';
    $subdir = $dir . DIRECTORY_SEPARATOR . 'empty_subdir';
    $subsubdir = $subdir . DIRECTORY_SEPARATOR . 'empty_sub_subdir';
    mkdir($subdir, 0777, TRUE);
    mkdir($subsubdir, 0777, TRUE);
    $file = $dir . DIRECTORY_SEPARATOR . 'file.txt';
    file_put_contents($file, 'test');

    // Create a symlink directory.
    $symlink_target = $dir . DIRECTORY_SEPARATOR . 'symlink_target';
    mkdir($symlink_target, 0777, TRUE);
    $symlink_dir = $dir . DIRECTORY_SEPARATOR . 'symlink_dir';
    symlink($symlink_target, $symlink_dir);

    $this->assertDirectoryExists($dir);
    $this->assertDirectoryExists($subdir);
    $this->assertTrue(is_link($symlink_dir));

    // Test that rmdirEmpty works on real directories but not on symlinks.
    File::rmdirEmpty($subdir);
    File::rmdirEmpty($subsubdir);
    // Symlink should be skipped.
    File::rmdirEmpty($symlink_dir);

    $this->assertDirectoryDoesNotExist($subdir);
    $this->assertDirectoryExists($dir);
    $this->assertDirectoryExists($this->testTmpDir);
    // Symlink should still exist.
    $this->assertTrue(is_link($symlink_dir));

    // Clean up.
    File::rmdir($dir);
    $this->assertDirectoryDoesNotExist($dir);
  }

  public function testCopyIfExists(): void {
    $source_file = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source.txt';
    $dest_file = $this->testTmpDir . DIRECTORY_SEPARATOR . 'dest.txt';
    file_put_contents($source_file, 'test content');

    $result = File::copyIfExists($source_file, $dest_file);
    $this->assertTrue($result);
    $this->assertFileExists($dest_file);
    $this->assertEquals('test content', file_get_contents($dest_file));

    $nonexistent_source = $this->testTmpDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';
    $nonexistent_dest = $this->testTmpDir . DIRECTORY_SEPARATOR . 'nonexistent_dest.txt';

    $result = File::copyIfExists($nonexistent_source, $nonexistent_dest);
    $this->assertFalse($result);
    $this->assertFileDoesNotExist($nonexistent_dest);
  }

  public function testScandirRecursive(): void {
    $base_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'test_scandir';
    mkdir($base_dir, 0777, TRUE);

    $subdir1 = $base_dir . DIRECTORY_SEPARATOR . 'subdir1';
    $subdir2 = $base_dir . DIRECTORY_SEPARATOR . 'subdir2';
    $ignored_dir = $base_dir . DIRECTORY_SEPARATOR . 'ignored_dir';
    mkdir($subdir1, 0777);
    mkdir($subdir2, 0777);
    mkdir($ignored_dir, 0777);

    file_put_contents($base_dir . DIRECTORY_SEPARATOR . 'file1.txt', 'test content');
    file_put_contents($subdir1 . DIRECTORY_SEPARATOR . 'file2.txt', 'test content');
    file_put_contents($subdir2 . DIRECTORY_SEPARATOR . 'file3.txt', 'test content');
    file_put_contents($ignored_dir . DIRECTORY_SEPARATOR . 'ignored_file.txt', 'test content');

    $files = File::scandirRecursive($base_dir);
    $this->assertCount(4, $files);

    $files = File::scandirRecursive($base_dir, ['ignored_dir']);
    $this->assertCount(3, $files);

    $files = File::scandirRecursive($base_dir, [], TRUE);
    $this->assertCount(7, $files);

    $files = File::scandirRecursive($base_dir . DIRECTORY_SEPARATOR . 'nonexistent');
    $this->assertEmpty($files);

    $file_path = $base_dir . DIRECTORY_SEPARATOR . 'file1.txt';
    $files = File::scandirRecursive($file_path);
    $this->assertEmpty($files);
  }

  public function testReplaceContent(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'test_replace.txt';
    file_put_contents($file_path, 'Hello, world!');

    File::replaceContent($file_path, 'world', 'everyone');
    $this->assertEquals('Hello, everyone!', file_get_contents($file_path));

    File::replaceContent($file_path, '/Hello, (\w+)!/', 'Greetings, $1!');
    $this->assertEquals('Greetings, everyone!', file_get_contents($file_path));

    $empty_file = $this->testTmpDir . DIRECTORY_SEPARATOR . 'empty.txt';
    file_put_contents($empty_file, '');
    File::replaceContent($empty_file, 'test', 'replacement');
    $this->assertEquals('', file_get_contents($empty_file));

    $nonexistent_file = $this->testTmpDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';
    File::replaceContent($nonexistent_file, 'test', 'replacement');
    $this->assertFileDoesNotExist($nonexistent_file);

    $image_file = $this->testTmpDir . DIRECTORY_SEPARATOR . 'test.jpg';
    file_put_contents($image_file, 'fake image content');
    File::replaceContent($image_file, 'fake', 'real');
    $this->assertEquals('fake image content', file_get_contents($image_file));
  }

  public function testRemoveToken(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'test_token.txt';
    $content = <<<EOT
This is line 1
#; TOKEN_START
This is content inside a token
#; TOKEN_END
This is line 3
#; ANOTHER_TOKEN
More content inside another token
#; ANOTHER_TOKEN
Final line
EOT;
    file_put_contents($file_path, $content);

    File::removeToken($file_path, '#; TOKEN_START', '#; TOKEN_END', FALSE);
    $file_content = (string) file_get_contents($file_path);
    $this->assertStringNotContainsString('#; TOKEN_START', $file_content);
    $this->assertStringNotContainsString('#; TOKEN_END', $file_content);
    $this->assertStringContainsString('This is content inside a token', $file_content);

    File::removeToken($file_path, '#; ANOTHER_TOKEN', '#; ANOTHER_TOKEN', TRUE);
    $file_content = (string) file_get_contents($file_path);
    $this->assertStringNotContainsString('#; ANOTHER_TOKEN', $file_content);
    $this->assertStringNotContainsString('More content inside another token', $file_content);

    $mismatched_file = $this->testTmpDir . DIRECTORY_SEPARATOR . 'mismatched_tokens.txt';
    $mismatched_content = <<<EOT
START
START
END
EOT;
    file_put_contents($mismatched_file, $mismatched_content);

    $this->expectException(\RuntimeException::class);
    File::removeToken($mismatched_file, 'START', 'END', FALSE);
  }

  public function testDiff(): void {
    $baseline_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'baseline';
    $destination_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';
    $diff_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'diff';

    mkdir($baseline_dir, 0777, TRUE);
    mkdir($destination_dir, 0777, TRUE);

    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'common.txt', 'Common content');
    file_put_contents($destination_dir . DIRECTORY_SEPARATOR . 'common.txt', 'Common content');

    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'modified.txt', 'Original content');
    file_put_contents($destination_dir . DIRECTORY_SEPARATOR . 'modified.txt', 'Modified content');

    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'removed.txt', 'This file is removed');

    file_put_contents($destination_dir . DIRECTORY_SEPARATOR . 'added.txt', 'This file is added');

    mkdir($baseline_dir . DIRECTORY_SEPARATOR . 'subdir', 0777);
    mkdir($destination_dir . DIRECTORY_SEPARATOR . 'subdir', 0777);
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'subfile.txt', 'Subfile content');
    file_put_contents($destination_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'subfile.txt', 'Changed subfile content');

    File::diff($baseline_dir, $destination_dir, $diff_dir);

    $this->assertFileExists($diff_dir . DIRECTORY_SEPARATOR . 'added.txt');
    $this->assertFileExists($diff_dir . DIRECTORY_SEPARATOR . '-removed.txt');
    $this->assertFileExists($diff_dir . DIRECTORY_SEPARATOR . 'modified.txt');
    $this->assertFileExists($diff_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'subfile.txt');
  }

  public function testCopy(): void {
    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';

    mkdir($source_dir, 0777, TRUE);

    file_put_contents($source_dir . DIRECTORY_SEPARATOR . 'file.txt', 'file content');

    $symlink_target = $source_dir . DIRECTORY_SEPARATOR . 'target.txt';
    $symlink = $source_dir . DIRECTORY_SEPARATOR . 'symlink.txt';
    file_put_contents($symlink_target, 'target content');
    symlink($symlink_target, $symlink);

    $subdir = $source_dir . DIRECTORY_SEPARATOR . 'subdir';
    mkdir($subdir, 0777);
    file_put_contents($subdir . DIRECTORY_SEPARATOR . 'subfile.txt', 'subfile content');

    $result = File::copy($source_dir . DIRECTORY_SEPARATOR . 'file.txt', $dest_dir . DIRECTORY_SEPARATOR . 'file.txt');
    $this->assertTrue($result);
    $this->assertFileExists($dest_dir . DIRECTORY_SEPARATOR . 'file.txt');
    $this->assertEquals('file content', file_get_contents($dest_dir . DIRECTORY_SEPARATOR . 'file.txt'));

    $dest_symlink = $dest_dir . DIRECTORY_SEPARATOR . 'symlink.txt';
    $result = File::copy($symlink, $dest_symlink);
    $this->assertTrue($result);
    $this->assertTrue(is_link($dest_symlink));

    // Test copying directory with copy_empty_dirs = TRUE.
    $result = File::copy($subdir, $dest_dir . DIRECTORY_SEPARATOR . 'subdir', 0755, TRUE);
    $this->assertTrue($result);
    $this->assertDirectoryExists($dest_dir . DIRECTORY_SEPARATOR . 'subdir');
    $this->assertFileExists($dest_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'subfile.txt');
  }

  public function testScandirRecursiveEmptyDirectory(): void {
    // Create directory and make it unreadable to simulate empty scandir.
    $empty_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'empty_dir_test';
    mkdir($empty_dir, 0777);

    // Create a directory and then remove it to test directory not existing.
    $removed_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'removed_dir';
    mkdir($removed_dir, 0777);
    rmdir($removed_dir);

    $files = File::scandirRecursive($removed_dir);
    $this->assertEmpty($files);
  }

  public function testScandirRecursiveActuallyEmptyDirectory(): void {
    // Test case for a directory that exists but contains no files/subdirs
    // This covers the line where we check if $paths is empty after removing
    // . and ..
    $empty_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'truly_empty_dir';
    mkdir($empty_dir, 0777);

    // Ensure directory exists but has no contents (except . and ..)
    $this->assertDirectoryExists($empty_dir);

    // This should hit the new condition: if (empty($paths)) { return []; }.
    $files = File::scandirRecursive($empty_dir);
    $this->assertEmpty($files);

    // Clean up.
    rmdir($empty_dir);
  }

}
