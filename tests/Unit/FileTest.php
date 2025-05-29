<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Exception\FileException;
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

  public function testCwd(): void {
    // Store the original working directory.
    $original_cwd = getcwd();
    $this->assertNotFalse($original_cwd, 'Failed to get current working directory');

    // Verify that File::cwd() returns the current directory.
    $file_cwd = File::cwd();
    $this->assertSame(File::realpath($original_cwd), $file_cwd);

    // Create a temporary directory to change to.
    $temp_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'cwd_test';
    mkdir($temp_dir, 0777, TRUE);
    $this->assertDirectoryExists($temp_dir);

    // Change to the temporary directory.
    $change_result = chdir($temp_dir);
    $this->assertTrue($change_result, 'Failed to change directory');

    // Verify that File::cwd() now returns the new directory.
    $new_file_cwd = File::cwd();
    $expected_new_cwd = File::realpath($temp_dir);
    $this->assertSame($expected_new_cwd, $new_file_cwd);

    // Verify that getcwd() and File::cwd() are in sync.
    $php_cwd = getcwd();
    $this->assertNotFalse($php_cwd, 'Failed to get current working directory after chdir');
    $this->assertSame(File::realpath($php_cwd), $new_file_cwd);

    // Restore the original working directory.
    $restore_result = chdir($original_cwd);
    $this->assertTrue($restore_result, 'Failed to restore original directory');

    // Verify that File::cwd() returns the original directory again.
    $restored_file_cwd = File::cwd();
    $this->assertSame(File::realpath($original_cwd), $restored_file_cwd);
  }

  #[DataProvider('dataProviderRealpath')]
  public function testRealpath(string $path, string $expected): void {
    $this->assertSame($expected, File::realpath($path));
  }

  public static function dataProviderRealpath(): array {
    $cwd = getcwd();

    if ($cwd === FALSE) {
      throw new FileException('Failed to determine current working directory.');
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
      $this->expectException(FileException::class);
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
      $this->expectException(FileException::class);
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

  public function testMkdirThrowsSpecificExceptionForExistingFile(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'test_file.txt';
    file_put_contents($file_path, 'test content');
    $this->assertFileExists($file_path);

    $this->expectException(FileException::class);
    $this->expectExceptionMessage('Cannot create directory "' . realpath($file_path) . '": path exists and is a file.');

    File::mkdir($file_path);
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
