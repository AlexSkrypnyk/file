<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(File::class)]
#[CoversMethod(File::class, 'cwd')]
#[CoversMethod(File::class, 'realpath')]
class FileTest extends UnitTestBase {

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

    // Create a real file and a symlink for testing.
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
  public function testDir(string $directory, bool $create, int $permissions, bool $should_exist, bool $expect_exception): void {
    if ($expect_exception) {
      $this->expectException(\RuntimeException::class);
    }

    $path = $this->testTmpDir . DIRECTORY_SEPARATOR . $directory;
    if (basename($path) === 'existing_file') {
      touch($path);
      $this->assertFalse(File::dirIsEmpty($path));
      $this->assertTrue(File::exists($path));
    }
    elseif (basename($path) === 'existing_dir') {
      mkdir($path, 0777, TRUE);
      $this->assertTrue(File::dirIsEmpty($path));
      $this->assertTrue(File::exists($path));
    }
    else {
      $this->assertFalse(File::exists($path));
    }

    $createdDir = File::dir($path, $create, $permissions);

    if (!$expect_exception) {
      $this->assertDirectoryExists($createdDir);
      $this->assertSame(realpath($createdDir), realpath($path));
      $this->assertEquals($should_exist, File::exists($path));
    }
  }

  public static function dataProviderDir(): array {
    return [
      ['existing_dir', TRUE, 0777, TRUE, FALSE],
      ['existing_dir', FALSE, 0777, TRUE, FALSE],
      ['non_existing_dir', TRUE, 0777, TRUE, FALSE],
      ['non_existing_dir', FALSE, 0777, FALSE, TRUE],
      ['existing_file', TRUE, 0777, TRUE, TRUE],
      ['existing_file', FALSE, 0777, TRUE, TRUE],
    ];
  }

  #[DataProvider('dataProviderTmpDir')]
  public function testTmpDir(?string $directory, string $prefix): void {
    $path = File::tmpdir($directory, $prefix);

    $this->assertNotEmpty($prefix);
    $expected_base_dir = realpath($directory ?? sys_get_temp_dir());
    $this->assertNotEmpty($expected_base_dir);
    $this->assertDirectoryExists($path);
    $this->assertStringStartsWith($prefix, basename($path));
    $this->assertStringStartsWith($expected_base_dir, dirname($path));

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
  public function testFindMatchingPath(array|string $paths, ?string $needle, ?string $expectedFile): void {
    $result = File::findMatchingPath($paths, $needle);

    if ($expectedFile) {
      $this->assertNotNull($result);
      $this->assertFileExists($result);
      $this->assertSame(realpath($expectedFile), realpath($result));
    }
    else {
      $this->assertNull($result);
    }
  }

  public static function dataProviderFindMatchingPath(): array {
    $testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_test_', TRUE);
    mkdir($testDir, 0777, TRUE);

    $file1 = $testDir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $testDir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, "This is a test file containing needle.");
    file_put_contents($file2, "This is another file without the word.");

    return [
      'single path, no needle' => [$file1, NULL, $file1],
      'single path, with needle' => [$file1, 'needle', $file1],
      'single path, needle missing' => [$file2, 'needle', NULL],
      'glob pattern, first match' => [$testDir . DIRECTORY_SEPARATOR . '*.txt', NULL, $file1],
      'glob pattern, matching content' => [$testDir . DIRECTORY_SEPARATOR . '*.txt', 'needle', $file1],
      'glob pattern, no match' => [$testDir . DIRECTORY_SEPARATOR . '*.md', NULL, NULL],
    ];
  }

  public function testRmdirAndRmdirEmpty(): void {
    // Create a directory with a nested empty subdirectory.
    $dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'test_dir';
    $subdir = $dir . DIRECTORY_SEPARATOR . 'empty_subdir';
    mkdir($subdir, 0777, TRUE);
    $file = $dir . DIRECTORY_SEPARATOR . 'file.txt';
    file_put_contents($file, 'test');

    // Ensure directory and subdirectory exist.
    $this->assertDirectoryExists($dir);
    $this->assertDirectoryExists($subdir);

    File::rmdirEmpty($subdir);

    $this->assertDirectoryDoesNotExist($subdir);
    // Parent should remain.
    $this->assertDirectoryExists($dir);
    // Parent should remain.
    $this->assertDirectoryExists($this->testTmpDir);

    // Test rmdir() - should remove everything.
    File::rmdir($dir);
    $this->assertDirectoryDoesNotExist($dir);
  }

}
