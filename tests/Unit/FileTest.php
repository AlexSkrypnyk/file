<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Exception\FileException;
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
#[CoversMethod(File::class, 'rmdirIfEmpty')]
#[CoversMethod(File::class, 'findMatchingPath')]
#[CoversMethod(File::class, 'copyIfExists')]
#[CoversMethod(File::class, 'scandir')]
#[CoversMethod(File::class, 'tmpdir')]
#[CoversMethod(File::class, 'copy')]
#[CoversMethod(File::class, 'append')]
#[CoversMethod(File::class, 'remove')]
final class FileTest extends UnitTestCase {

  protected string $testTmpDir;

  #[\Override]
  protected function setUp(): void {
    $this->testTmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_test_', TRUE);
    mkdir($this->testTmpDir, 0777, TRUE);
  }

  #[\Override]
  protected function tearDown(): void {
    File::resetSymlinkMode();

    if (is_dir($this->testTmpDir)) {
      File::remove($this->testTmpDir);
    }
  }

  public function testCwd(): void {
    $original_cwd = getcwd();
    $this->assertNotFalse($original_cwd, 'Failed to get current working directory');

    $file_cwd = File::cwd();
    $this->assertSame(File::realpath($original_cwd), $file_cwd);

    $temp_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'cwd_test';
    mkdir($temp_dir, 0777, TRUE);
    $this->assertDirectoryExists($temp_dir);

    $change_result = chdir($temp_dir);
    $this->assertTrue($change_result, 'Failed to change directory');

    $new_file_cwd = File::cwd();
    $expected_new_cwd = File::realpath($temp_dir);
    $this->assertSame($expected_new_cwd, $new_file_cwd);

    $php_cwd = getcwd();
    $this->assertNotFalse($php_cwd, 'Failed to get current working directory after chdir');
    $this->assertSame(File::realpath($php_cwd), $new_file_cwd);

    $restore_result = chdir($original_cwd);
    $this->assertTrue($restore_result, 'Failed to restore original directory');

    $restored_file_cwd = File::cwd();
    $this->assertSame(File::realpath($original_cwd), $restored_file_cwd);
  }

  #[DataProvider('dataProviderRealpath')]
  public function testRealpath(string $path, string $expected): void {
    $this->assertSame($expected, File::realpath($path));
  }

  public static function dataProviderRealpath(): \Iterator {
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

    // Absolute paths remain unchanged.
    yield ['/var/www/file.txt', self::rooted('/var/www/file.txt')];

    // Relative path resolved from current working directory.
    yield ['file.txt', $cwd . DIRECTORY_SEPARATOR . 'file.txt'];

    // Parent directory resolution.
    yield ['../file.txt', dirname($cwd) . DIRECTORY_SEPARATOR . 'file.txt'];
    yield ['./file.txt', $cwd . DIRECTORY_SEPARATOR . 'file.txt'];

    // Temporary directory resolution.
    yield [$tmp_dir . DIRECTORY_SEPARATOR . 'file.txt', $tmp_realpath . DIRECTORY_SEPARATOR . 'file.txt'];

    // Symlink resolution.
    yield [$symlink_path, $symlink_target];

    // Stream wrapper URLs keep their scheme separator.
    yield ['vfs://run/out/nested', 'vfs://run/out/nested'];
    yield ['vfs://', 'vfs://'];
    yield ['file:///var/www/file.txt', 'file:///var/www/file.txt'];
    yield ['php://memory', 'php://memory'];
    yield ['s3://bucket/key', 's3://bucket/key'];
    yield ['compress.zlib://var/log/file.gz', 'compress.zlib://var/log/file.gz'];

    // Normalisation applies to the path after the scheme.
    yield ['vfs://run//out', 'vfs://run/out'];
    yield ['vfs://run/./out', 'vfs://run/out'];
    yield ['vfs://run/out/../other', 'vfs://run/other'];
    yield ['vfs://run/../..', 'vfs://'];

    // A Windows drive letter is not a stream wrapper scheme.
    yield ['C://foo', 'C:' . DIRECTORY_SEPARATOR . 'foo'];
  }

  public function testMkdirStreamWrapperUrl(): void {
    $original_cwd = getcwd();
    $this->assertNotFalse($original_cwd, 'Failed to get current working directory');

    $sandbox = $this->testTmpDir . DIRECTORY_SEPARATOR . 'stream_wrapper';
    mkdir($sandbox, 0777, TRUE);
    $this->assertTrue(chdir($sandbox), 'Failed to change directory');

    try {
      // A URL path uses forward slashes on every platform.
      $url = 'file://' . str_replace('\\', '/', File::realpath($sandbox)) . '/out/nested';

      $this->assertSame($url, File::absolute($url));
      $this->assertSame($url, File::mkdir($url));
      $this->assertDirectoryExists($sandbox . DIRECTORY_SEPARATOR . 'out' . DIRECTORY_SEPARATOR . 'nested');
      $this->assertDirectoryDoesNotExist($sandbox . DIRECTORY_SEPARATOR . 'file:');
    }
    finally {
      chdir($original_cwd);
    }
  }

  #[DataProvider('dataProviderAbsolute')]
  public function testAbsolute(string $file, ?string $base, string $expected): void {
    $this->assertSame($expected, File::absolute($file, $base));
  }

  public static function dataProviderAbsolute(): \Iterator {
    // Absolute path remains unchanged.
    yield ['/var/www/file.txt', NULL, self::rooted('/var/www/file.txt')];
    yield ['/var/www/file.txt', '/base/path', self::rooted('/var/www/file.txt')];
    // Relative path resolved from current working directory.
    yield ['file.txt', NULL, File::cwd() . DIRECTORY_SEPARATOR . 'file.txt'];
    // Relative path resolved from provided base path.
    yield ['file.txt', '/base/path', self::rooted('/base/path/file.txt')];
    // Handling nested relative paths.
    yield ['../file.txt', '/base/path/subdir', self::rooted('/base/path/file.txt')];
    // Stream wrapper URL is already absolute.
    yield ['vfs://run/out', NULL, 'vfs://run/out'];
    yield ['vfs://run/out', '/base/path', 'vfs://run/out'];
    // Relative path resolved from a stream wrapper base.
    yield ['file.txt', 'vfs://run', 'vfs://run/file.txt'];
  }

  #[DataProvider('dataProviderDir')]
  public function testDir(string $directory, bool $expect_exception): void {
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

  public static function dataProviderDir(): \Iterator {
    yield ['existing_dir', FALSE];
    yield ['non_existing_dir', TRUE];
    yield ['existing_file', TRUE];
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

  public static function dataProviderMkdir(): \Iterator {
    yield ['existing_dir', 0777, FALSE];
    yield ['non_existing_dir', 0777, FALSE];
    yield ['existing_file', 0777, TRUE];
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

  public static function dataProviderTmpDir(): \Iterator {
    $base = sys_get_temp_dir();
    yield 'default dir, default prefix' => [NULL, 'tmp_'];
    yield 'default dir, custom prefix' => [NULL, 'custom_'];
    yield 'custom dir, default prefix' => [$base . DIRECTORY_SEPARATOR . 'custom_dir', 'tmp_'];
    yield 'custom dir, custom prefix' => [$base . DIRECTORY_SEPARATOR . 'custom_dir', 'custom_'];
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

  public static function dataProviderFindMatchingPath(): \Iterator {
    $test_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_test_', TRUE);
    mkdir($test_dir, 0777, TRUE);

    $file1 = $test_dir . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $test_dir . DIRECTORY_SEPARATOR . 'file2.txt';
    $file3 = $test_dir . DIRECTORY_SEPARATOR . 'test_file.php';
    $file4 = $test_dir . DIRECTORY_SEPARATOR . 'Controller.php';

    file_put_contents($file1, 'This is a test file.');
    file_put_contents($file2, 'This is another file.');
    file_put_contents($file3, "<?php\necho 'test';");
    file_put_contents($file4, "<?php\nclass Controller {}");

    yield 'single path, no needle' => [$file1, NULL, $file1];
    yield 'single path, with needle in path' => [$file1, 'file1', $file1];
    yield 'single path, needle missing in path' => [$file2, 'nonexistent', NULL];
    yield 'glob pattern, first match' => [$test_dir . DIRECTORY_SEPARATOR . '*.txt', NULL, $file1];
    yield 'glob pattern, matching path' => [$test_dir . DIRECTORY_SEPARATOR . '*.txt', 'file1', $file1];
    yield 'glob pattern, no match' => [$test_dir . DIRECTORY_SEPARATOR . '*.md', NULL, NULL];
    yield 'regex: match .txt extension' => [$test_dir . DIRECTORY_SEPARATOR . 'file*.txt', '/file1\.txt$/', $file1];
    yield 'regex: match .php extension' => [$test_dir . DIRECTORY_SEPARATOR . 'test*.php', '/test_file\.php$/', $file3];
    yield 'regex: match Controller in path' => [$test_dir . DIRECTORY_SEPARATOR . '*.php', '/Controller\.php$/', $file4];
    yield 'regex: match file starting with test' => [$test_dir . DIRECTORY_SEPARATOR . 'test*', '/test_file/', $file3];
    yield 'regex: case-insensitive match' => [$test_dir . DIRECTORY_SEPARATOR . 'C*.php', '/controller/i', $file4];
    yield 'regex: no match' => [$test_dir . DIRECTORY_SEPARATOR . '*.txt', '/\.php$/', NULL];
    yield 'regex: alternative delimiter' => [$test_dir . DIRECTORY_SEPARATOR . 'file1*', '#file1\.txt#', $file1];
  }

  public function testRmdirAndRmdirEmpty(): void {
    $dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'test_dir';
    $subdir = $dir . DIRECTORY_SEPARATOR . 'empty_subdir';
    $subsubdir = $subdir . DIRECTORY_SEPARATOR . 'empty_sub_subdir';
    mkdir($subdir, 0777, TRUE);
    mkdir($subsubdir, 0777, TRUE);
    $file = $dir . DIRECTORY_SEPARATOR . 'file.txt';
    file_put_contents($file, 'test');

    $symlink_target = $dir . DIRECTORY_SEPARATOR . 'symlink_target';
    mkdir($symlink_target, 0777, TRUE);
    $symlink_dir = $dir . DIRECTORY_SEPARATOR . 'symlink_dir';
    symlink($symlink_target, $symlink_dir);

    $this->assertDirectoryExists($dir);
    $this->assertDirectoryExists($subdir);
    $this->assertTrue(is_link($symlink_dir));

    File::rmdirIfEmpty($subdir);
    File::rmdirIfEmpty($subsubdir);
    // File::rmdirIfEmpty() skips symlinks.
    File::rmdirIfEmpty($symlink_dir);

    $this->assertDirectoryDoesNotExist($subdir);
    $this->assertDirectoryExists($dir);
    $this->assertDirectoryExists($this->testTmpDir);
    $this->assertTrue(is_link($symlink_dir));

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
    $this->assertSame('test content', file_get_contents($dest_file));

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

    $files = File::scandir($base_dir);
    $this->assertCount(4, $files);

    $files = File::scandir($base_dir, ['ignored_dir']);
    $this->assertCount(3, $files);

    $files = File::scandir($base_dir, [], TRUE);
    $this->assertCount(7, $files);

    $files = File::scandir($base_dir . DIRECTORY_SEPARATOR . 'nonexistent');
    $this->assertEmpty($files);

    $file_path = $base_dir . DIRECTORY_SEPARATOR . 'file1.txt';
    $files = File::scandir($file_path);
    $this->assertEmpty($files);
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
    $this->assertSame('file content', file_get_contents($dest_dir . DIRECTORY_SEPARATOR . 'file.txt'));

    $dest_symlink = $dest_dir . DIRECTORY_SEPARATOR . 'symlink.txt';
    $result = File::copy($symlink, $dest_symlink);
    $this->assertTrue($result);
    $this->assertTrue(is_link($dest_symlink));

    // The TRUE argument enables copy_empty_dirs.
    $result = File::copy($subdir, $dest_dir . DIRECTORY_SEPARATOR . 'subdir', 0755, TRUE);
    $this->assertTrue($result);
    $this->assertDirectoryExists($dest_dir . DIRECTORY_SEPARATOR . 'subdir');
    $this->assertFileExists($dest_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'subfile.txt');
  }

  #[DataProvider('dataProviderCopyRelativeSymlink')]
  public function testCopyRelativeSymlink(array $links, string $link, string $expected_path): void {
    File::setSymlinkMode(File::SYMLINK_MODE_RELATIVE);

    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';

    $this->createFiles($source_dir, [
      'library/provision.sh' => 'provision',
      'hooks/run.sh' => 'run',
      'hooks/0' => 'zero',
      'hooks/update/script.sh' => 'script',
    ]);

    foreach ($links as $path => $target) {
      $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . self::nativePath($path), self::nativePath($target));
    }

    $this->assertTrue(File::copy($source_dir, $dest_dir));
    File::remove($source_dir);

    $dest_link = $dest_dir . DIRECTORY_SEPARATOR . self::nativePath($link);
    $this->assertTrue(is_link($dest_link), 'Copied symlink is a symlink.');

    $resolved = realpath($dest_link);
    $this->assertIsString($resolved, 'Copied symlink resolves after the source is removed.');
    $this->assertSame(realpath($dest_dir . DIRECTORY_SEPARATOR . self::nativePath($expected_path)), $resolved);

    // readlink() returns the resolved target on Windows.
    if (DIRECTORY_SEPARATOR === '/' && isset($links[$link])) {
      $this->assertSame($links[$link], readlink($dest_link));
    }
  }

  public static function dataProviderCopyRelativeSymlink(): \Iterator {
    yield 'file symlink to a sibling directory' => [['hooks/1.provision.sh' => '../library/provision.sh'], 'hooks/1.provision.sh', 'library/provision.sh'];
    yield 'file symlink in the same directory' => [['hooks/alias.sh' => 'run.sh'], 'hooks/alias.sh', 'hooks/run.sh'];
    yield 'file symlink to a file named 0' => [['hooks/zero.sh' => '0'], 'hooks/zero.sh', 'hooks/0'];
    yield 'file symlink from a nested directory' => [['hooks/a/b/link.sh' => '../../../library/provision.sh'], 'hooks/a/b/link.sh', 'library/provision.sh'];
    yield 'file symlink to a symlink copied later' => [['hooks/b.sh' => 'run.sh', 'hooks/a.sh' => 'b.sh'], 'hooks/a.sh', 'hooks/run.sh'];
    yield 'directory symlink to a sibling directory' => [['hooks/deploy' => 'update'], 'hooks/deploy', 'hooks/update'];
    yield 'directory symlink to its own directory' => [['hooks/self' => '.'], 'hooks/self', 'hooks'];
    yield 'directory symlink to the parent directory' => [['hooks/update/parent' => '..'], 'hooks/update/parent', 'hooks'];
    yield 'file symlink through a directory symlink' => [['hooks/deploy' => 'update', 'hooks/update/run.sh' => '../run.sh'], 'hooks/deploy/run.sh', 'hooks/run.sh'];
  }

  public function testCopySymlinkOutsideTree(): void {
    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';
    $outside_file = $this->testTmpDir . DIRECTORY_SEPARATOR . 'outside' . DIRECTORY_SEPARATOR . 'target.txt';

    $this->createFiles($source_dir, ['file.txt' => 'file']);
    File::dump($outside_file, 'outside');
    symlink($outside_file, $source_dir . DIRECTORY_SEPARATOR . 'link.txt');

    $this->assertTrue(File::copy($source_dir, $dest_dir));
    File::remove($source_dir);

    $dest_link = $dest_dir . DIRECTORY_SEPARATOR . 'link.txt';
    $this->assertTrue(is_link($dest_link), 'Copied symlink is a symlink.');
    $this->assertSame('outside', file_get_contents($dest_link));
  }

  #[DataProvider('dataProviderCopyFilter')]
  public function testCopyFilter(array $paths): void {
    File::setSymlinkMode(File::SYMLINK_MODE_RELATIVE);

    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';

    $this->createFiles($source_dir, [
      'library/provision.sh' => 'provision',
      'hooks/update/script.sh' => 'script',
      'hooks/skipped.sh' => 'skipped',
    ]);
    $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . self::nativePath('hooks/1.provision.sh'), self::nativePath('../library/provision.sh'));
    $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . self::nativePath('hooks/deploy'), 'update');

    $this->assertTrue(File::copy($source_dir, $dest_dir, filter: $paths));
    File::remove($source_dir);

    $this->assertFileDoesNotExist($dest_dir . DIRECTORY_SEPARATOR . self::nativePath('hooks/skipped.sh'));
    $this->assertSame('provision', file_get_contents($dest_dir . DIRECTORY_SEPARATOR . self::nativePath('hooks/1.provision.sh')));
    $this->assertSame('script', file_get_contents($dest_dir . DIRECTORY_SEPARATOR . self::nativePath('hooks/deploy/script.sh')));

    // readlink() returns the resolved target on Windows.
    if (DIRECTORY_SEPARATOR === '/') {
      $this->assertSame('../library/provision.sh', readlink($dest_dir . '/hooks/1.provision.sh'));
      $this->assertSame('update', readlink($dest_dir . '/hooks/deploy'));
    }
  }

  public static function dataProviderCopyFilter(): \Iterator {
    yield 'files and symlinks' => [['hooks/1.provision.sh', 'hooks/deploy', 'hooks/update/script.sh', 'library/provision.sh']];
    yield 'directories containing the symlink targets' => [['hooks/1.provision.sh', 'hooks/deploy', 'hooks/update', 'library']];
  }

  public function testCopyFilterCalls(): void {
    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';

    $this->createFiles($source_dir, [
      'file.txt' => 'file',
      'dir/inner.txt' => 'inner',
      'excluded/inner.txt' => 'excluded',
    ]);
    $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . 'dir_link', 'dir');

    $calls = [];
    $filter = function (string $path, bool $is_dir) use (&$calls): bool {
      $calls[$path] = $is_dir;

      return $path !== 'excluded';
    };

    $this->assertTrue(File::copy($source_dir, $dest_dir, filter: $filter));

    ksort($calls);
    $this->assertSame(['dir' => TRUE, 'dir/inner.txt' => FALSE, 'dir_link' => FALSE, 'excluded' => TRUE, 'file.txt' => FALSE], $calls, 'The rejected directory is not entered, and a symlink is not a directory.');
    $this->assertFileExists($dest_dir . DIRECTORY_SEPARATOR . self::nativePath('dir/inner.txt'));
    $this->assertTrue(is_link($dest_dir . DIRECTORY_SEPARATOR . 'dir_link'));
    $this->assertDirectoryDoesNotExist($dest_dir . DIRECTORY_SEPARATOR . 'excluded');
  }

  public function testCopyFilterRejectsEverything(): void {
    $this->createFiles($this->testTmpDir . DIRECTORY_SEPARATOR . 'source', ['file.txt' => 'file']);

    $this->assertTrue(File::copy($this->testTmpDir . DIRECTORY_SEPARATOR . 'source', $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination', filter: fn(): bool => FALSE));
    $this->assertDirectoryDoesNotExist($this->testTmpDir . DIRECTORY_SEPARATOR . 'destination');
  }

  public function testCopyFileSymlinkToDirectory(): void {
    // Only Windows has file symlinks to directories, and readlink() fails on
    // them. Archive extractors create one when the target is not extracted
    // yet.
    if (DIRECTORY_SEPARATOR !== '\\') {
      $this->markTestSkipped('Windows only.');
    }

    File::setSymlinkMode(File::SYMLINK_MODE_RELATIVE);

    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';

    $this->createFiles($source_dir, ['hooks/update/script.sh' => 'script']);
    exec(sprintf('cmd /c mklink %s update', escapeshellarg($source_dir . '\\hooks\\deploy')), $output, $exit_code);
    $this->assertSame(0, $exit_code, implode(PHP_EOL, $output));
    $this->assertDirectoryDoesNotExist($source_dir . '\\hooks\\deploy', 'Source symlink is a file symlink.');

    $this->assertTrue(File::copy($source_dir, $dest_dir));
    File::remove($source_dir);

    $dest_link = $dest_dir . '\\hooks\\deploy';
    $this->assertTrue(is_link($dest_link), 'Copied symlink is a symlink.');
    $this->assertDirectoryExists($dest_link);
    $this->assertSame('script', file_get_contents($dest_link . '\\script.sh'));
  }

  public function testCopyNativeWindowsSymlink(): void {
    if (DIRECTORY_SEPARATOR !== '\\') {
      $this->markTestSkipped('Windows only.');
    }

    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';

    $this->createFiles($source_dir, ['library/provision.sh' => 'provision']);
    $this->createSymlink($source_dir . '\\hooks\\1.provision.sh', '..\\library\\provision.sh');

    $this->assertTrue(File::copy($source_dir, $dest_dir));

    // The copied symlink reads the source file, not the copied one.
    File::dump($source_dir . '\\library\\provision.sh', 'changed');
    $this->assertSame('changed', file_get_contents($dest_dir . '\\hooks\\1.provision.sh'));
    $this->assertSame('provision', file_get_contents($dest_dir . '\\library\\provision.sh'));
  }

  public function testSymlinkMode(): void {
    $this->assertSame(File::SYMLINK_MODE_NATIVE, File::getSymlinkMode());

    File::setSymlinkMode(File::SYMLINK_MODE_RELATIVE);
    $this->assertSame(File::SYMLINK_MODE_RELATIVE, File::getSymlinkMode());

    File::resetSymlinkMode();
    $this->assertSame(File::SYMLINK_MODE_NATIVE, File::getSymlinkMode());
  }

  public function testCopySymlinksToRelativeDestination(): void {
    $this->createFiles($this->testTmpDir . DIRECTORY_SEPARATOR . 'source', [
      'library/provision.sh' => 'provision',
      'hooks/run.sh' => 'run',
    ]);
    $this->createSymlink($this->testTmpDir . DIRECTORY_SEPARATOR . self::nativePath('source/hooks/1.provision.sh'), self::nativePath('../library/provision.sh'));
    $this->createSymlink($this->testTmpDir . DIRECTORY_SEPARATOR . self::nativePath('source/library/run.sh'), self::nativePath('../hooks/run.sh'));

    $current_dir = getcwd();
    $this->assertIsString($current_dir, 'Failed to get current working directory');
    chdir($this->testTmpDir);

    $result = File::copy('source', 'destination');
    $cwd_after_copy = getcwd();
    chdir($current_dir);

    $this->assertTrue($result);
    $this->assertSame(realpath($this->testTmpDir), realpath((string) $cwd_after_copy), 'Working directory is restored.');
    $this->assertSame('provision', file_get_contents($this->testTmpDir . DIRECTORY_SEPARATOR . self::nativePath('destination/hooks/1.provision.sh')));
    $this->assertSame('run', file_get_contents($this->testTmpDir . DIRECTORY_SEPARATOR . self::nativePath('destination/library/run.sh')));
  }

  public function testCopySymlinkFailure(): void {
    // Windows ignores directory permissions, and root bypasses them.
    if (DIRECTORY_SEPARATOR === '\\' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
      $this->markTestSkipped('Directory permissions do not prevent symlink creation.');
    }

    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';

    $this->createFiles($source_dir, ['file.txt' => 'file']);
    $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . 'link.txt', 'file.txt');
    mkdir($dest_dir);
    chmod($dest_dir, 0555);

    $exception = NULL;
    try {
      File::copy($source_dir . DIRECTORY_SEPARATOR . 'link.txt', $dest_dir . DIRECTORY_SEPARATOR . 'link.txt');
    }
    catch (FileException $file_exception) {
      $exception = $file_exception;
    }
    chmod($dest_dir, 0755);

    $this->assertInstanceOf(FileException::class, $exception);
    $this->assertSame(sprintf('Unable to create symlinks: "%s" -> "file.txt".', $dest_dir . DIRECTORY_SEPARATOR . 'link.txt'), $exception->getMessage());
    $this->assertFalse(is_link($dest_dir . DIRECTORY_SEPARATOR . 'link.txt'));
  }

  #[DataProvider('dataProviderRelativeSymlinkTarget')]
  public function testRelativeSymlinkTarget(string $target, string $link_dir, string $root, bool $ignore_case, string $expected): void {
    $this->assertSame($expected, self::callProtectedMethod(File::class, 'relativeSymlinkTarget', [$target, $link_dir, $root, $ignore_case]));
  }

  public static function dataProviderRelativeSymlinkTarget(): \Iterator {
    yield 'file in a sibling directory' => ['/x/src/library/provision.sh', '/x/src/hooks', '/x/src', FALSE, '../library/provision.sh'];
    yield 'file in the same directory' => ['/x/src/hooks/run.sh', '/x/src/hooks', '/x/src', FALSE, 'run.sh'];
    yield 'file in a nested directory' => ['/x/src/hooks/a/b/run.sh', '/x/src/hooks', '/x/src', FALSE, 'a/b/run.sh'];
    yield 'directory the symlink is in' => ['/x/src/hooks', '/x/src/hooks', '/x/src', FALSE, '.'];
    yield 'root directory' => ['/x/src', '/x/src/hooks', '/x/src', FALSE, '..'];
    yield 'root with a trailing slash' => ['/x/src/hooks/run.sh', '/x/src/hooks', '/x/src/', FALSE, 'run.sh'];
    yield 'root in a different case, ignoring case' => ['/x/src/hooks/run.sh', '/x/src/hooks', '/X/SRC', TRUE, 'run.sh'];
    yield 'root in a different case, matching case' => ['/x/src/hooks/run.sh', '/x/src/hooks', '/X/SRC', FALSE, '/x/src/hooks/run.sh'];
    yield 'shared directory in a different case, ignoring case' => ['/x/SRC/library/provision.sh', '/x/src/hooks', '/x/src', TRUE, '../library/provision.sh'];
    yield 'shared directory in a different case, matching case' => ['/x/SRC/library/provision.sh', '/x/src/hooks', '/x/src', FALSE, '/x/SRC/library/provision.sh'];
    yield 'file outside the root' => ['/x/other/run.sh', '/x/src/hooks', '/x/src', FALSE, '/x/other/run.sh'];
    yield 'root parent directory' => ['/x', '/x/src/hooks', '/x/src', FALSE, '/x'];
    yield 'directory sharing the root prefix' => ['/x/src2/run.sh', '/x/src/hooks', '/x/src', FALSE, '/x/src2/run.sh'];
  }

  #[DataProvider('dataProviderSymlinkPermutations')]
  public function testSymlinkPermutations(string $operation, bool $is_dir, bool $absolute, string $target, string $link, bool $chained, bool $dangling): void {
    // A filtered copy sets the relative mode for the call only.
    File::setSymlinkMode($operation === 'copy' ? File::SYMLINK_MODE_RELATIVE : File::SYMLINK_MODE_NATIVE);

    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    $dest = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';
    $target_path = $tree . DIRECTORY_SEPARATOR . self::nativePath($target);
    $link_path = $tree . DIRECTORY_SEPARATOR . self::nativePath($link);
    $hop_path = dirname($link_path) . DIRECTORY_SEPARATOR . 'hop';
    $outside = str_starts_with($target, '../');

    // A target outside the tree is reached through the tree directory.
    File::mkdir($tree);
    $this->createTarget($target_path, $is_dir, $target);
    $target_path = (string) realpath($target_path);

    if ($chained) {
      $this->createSymlink($hop_path, $this->symlinkTargetString($target_path, $hop_path, $absolute));
      $this->createSymlink($link_path, $this->symlinkTargetString($hop_path, $link_path, $absolute));
    }
    else {
      $this->createSymlink($link_path, $this->symlinkTargetString($target_path, $link_path, $absolute));
    }

    if ($dangling) {
      File::remove($target_path);
    }

    $this->assertTrue($operation === 'copy' ? File::copy($tree, $dest) : File::copy($tree, $dest, filter: $this->listTree($tree), symlink_mode: File::SYMLINK_MODE_RELATIVE));
    $this->assertSame($operation === 'copy' ? File::SYMLINK_MODE_RELATIVE : File::SYMLINK_MODE_NATIVE, File::getSymlinkMode(), 'The global symlink mode is unchanged.');

    // A dangling copy proves it stays inside the destination by reading a
    // target restored there while the source target is still missing.
    if ($dangling) {
      $this->createTarget($outside ? $target_path : $dest . DIRECTORY_SEPARATOR . self::nativePath($target), $is_dir, $target);
    }
    else {
      File::remove($tree);
    }

    foreach ($chained ? [$link_path, $hop_path] : [$link_path] as $path) {
      $copied = $dest . substr($path, strlen($tree));
      $this->assertTrue(is_link($copied), sprintf('"%s" is a symlink.', $copied));
      $this->assertSame($target, file_get_contents($is_dir ? $copied . DIRECTORY_SEPARATOR . 'inner.txt' : $copied), sprintf('"%s" reads its target without the source target.', $copied));
    }

    // Restoring the source target leaves no dangling symlink to clean up.
    if ($dangling && !$outside) {
      $this->createTarget($target_path, $is_dir, $target);
    }
  }

  public static function dataProviderSymlinkPermutations(): \Iterator {
    $locations = [
      'same directory' => ['a/b/target', 'a/b/link'],
      'child' => ['a/b/c/target', 'a/b/link'],
      'parent' => ['a/target', 'a/b/link'],
      'sibling' => ['a/s/target', 'a/b/link'],
      'tree root' => ['target', 'a/b/link'],
      'deep descendant' => ['a/b/c/d/e/target', 'a/b/link'],
      'distant cousin' => ['x/y/z/target', 'a/b/link'],
      'link at the tree root' => ['a/b/target', 'link'],
      'outside the tree' => ['../outside/target', 'a/b/link'],
    ];

    foreach (['copy', 'filtered copy'] as $operation) {
      foreach (['file' => FALSE, 'directory' => TRUE] as $kind => $is_dir) {
        foreach (['relative' => FALSE, 'absolute' => TRUE] as $style => $absolute) {
          foreach ($locations as $location => [$target, $link]) {
            foreach (['direct' => FALSE, 'through a second link' => TRUE] as $shape => $chained) {
              foreach (['target present' => FALSE, 'dangling in the source' => TRUE] as $state => $dangling) {
                yield sprintf('%s, %s link, %s target, %s, %s, %s', $operation, $kind, $style, $location, $shape, $state) => [$operation, $is_dir, $absolute, $target, $link, $chained, $dangling];
              }
            }
          }
        }
      }
    }
  }

  #[DataProvider('dataProviderSymlinkSpecialCases')]
  public function testSymlinkSpecialCases(string $operation, array $files, array $links, string $check, string $expected): void {
    // A filtered copy sets the relative mode for the call only.
    File::setSymlinkMode($operation === 'copy' ? File::SYMLINK_MODE_RELATIVE : File::SYMLINK_MODE_NATIVE);

    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    $dest = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';

    $this->createFiles($tree, $files);
    foreach ($links as $path => $target) {
      $this->createSymlink($tree . DIRECTORY_SEPARATOR . self::nativePath($path), self::nativePath($target));
    }

    $this->assertTrue($operation === 'copy' ? File::copy($tree, $dest) : File::copy($tree, $dest, filter: $this->listTree($tree), symlink_mode: File::SYMLINK_MODE_RELATIVE));
    File::remove($tree);

    $this->assertSame($expected, file_get_contents($dest . DIRECTORY_SEPARATOR . self::nativePath($check)));
  }

  public static function dataProviderSymlinkSpecialCases(): \Iterator {
    $cases = [
      'name with spaces' => [['a/b/my target.txt' => 'spaces'], ['a/b/my link' => 'my target.txt'], 'a/b/my link', 'spaces'],
      'non-ASCII name' => [['a/b/ціль.txt' => 'unicode'], ['a/b/посилання' => 'ціль.txt'], 'a/b/посилання', 'unicode'],
      'target named 0' => [['a/b/0' => 'zero'], ['a/b/zero' => '0'], 'a/b/zero', 'zero'],
      'dotted and dash-prefixed names' => [['a/b/-target.txt' => 'dash'], ['a/b/.hidden' => '-target.txt'], 'a/b/.hidden', 'dash'],
      'link through a directory link' => [['a/b/target.txt' => 'through'], ['a/b/link' => 'target.txt', 'a/d' => 'b'], 'a/d/link', 'through'],
      'link to the tree root' => [['marker.txt' => 'root'], ['a/b/root' => '../..'], 'a/b/root/marker.txt', 'root'],
    ];

    foreach (['copy', 'filtered copy'] as $operation) {
      foreach ($cases as $name => $case) {
        yield sprintf('%s, %s', $operation, $name) => [$operation, ...$case];
      }
    }
  }

  #[DataProvider('dataProviderCopyFilterMissingTarget')]
  public function testCopyFilterMissingTarget(string $mode, bool $is_dir, bool $broken): void {
    if ($mode === File::SYMLINK_MODE_NATIVE && !$broken && DIRECTORY_SEPARATOR === '\\') {
      $this->markTestSkipped('File::SYMLINK_MODE_NATIVE on Windows points the symlink at the source target.');
    }

    File::setSymlinkMode($mode);

    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    $dest = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';
    $target = $tree . DIRECTORY_SEPARATOR . self::nativePath('a/target');

    File::dump($is_dir ? $target . DIRECTORY_SEPARATOR . 'inner.txt' : $target, 'source');
    $this->createSymlink($tree . DIRECTORY_SEPARATOR . self::nativePath('a/b/link'), self::nativePath('../target'));

    if ($broken) {
      File::remove($target);
    }

    // The filter leaves the target out of the copy.
    $this->assertTrue(File::copy($tree, $dest, filter: ['a/b/link']));
    File::remove($tree);

    $copied_target = $dest . DIRECTORY_SEPARATOR . self::nativePath('a/target');
    $copied_link = $dest . DIRECTORY_SEPARATOR . self::nativePath('a/b/link');
    $this->assertFileDoesNotExist($copied_target);
    $this->assertSame([], array_values(array_diff((array) scandir(dirname($copied_target)), ['.', '..', 'b'])), 'Nothing is created at the target.');

    // PHP on Windows does not report a dangling relative symlink as a link,
    // so restoring the target shows where the symlink points.
    File::dump($is_dir ? $copied_target . DIRECTORY_SEPARATOR . 'inner.txt' : $copied_target, 'restored');
    $this->assertTrue(is_link($copied_link));
    $this->assertSame('restored', file_get_contents($is_dir ? $copied_link . DIRECTORY_SEPARATOR . 'inner.txt' : $copied_link));
  }

  public static function dataProviderCopyFilterMissingTarget(): \Iterator {
    foreach (['native', 'relative'] as $mode) {
      yield $mode . ', file target left out' => [$mode, FALSE, FALSE];
      yield $mode . ', directory target left out' => [$mode, TRUE, FALSE];
      yield $mode . ', broken symlink' => [$mode, FALSE, TRUE];
    }
  }

  #[DataProvider('dataProviderParseWindowsSymlinkListing')]
  public function testParseWindowsSymlinkListing(string $name, ?array $expected): void {
    $lines = [
      ' Volume in drive C is Windows',
      ' Directory of C:\\work\\hooks',
      '',
      '11/13/2026  10:09 AM    <SYMLINKD>     deploy [update]',
      '11/13/2026  10:09 AM    <SYMLINK>      1.provision.sh [..\\library\\provision.sh]',
      '11/13/2026  10:09 AM    <SYMLINK>      my link [my [target].txt]',
      '11/13/2026  10:09 AM    <SYMLINK>      link2 [b.txt]',
      '11/13/2026  10:09 AM    <JUNCTION>     junction [C:\\work\\dir]',
      '               2 File(s)              0 bytes',
    ];

    $this->assertSame($expected, self::callProtectedMethod(File::class, 'parseWindowsSymlinkListing', [$lines, $name]));
  }

  public static function dataProviderParseWindowsSymlinkListing(): \Iterator {
    yield 'directory symlink' => ['deploy', ['update', TRUE]];
    yield 'file symlink with a relative target' => ['1.provision.sh', ['..\\library\\provision.sh', FALSE]];
    yield 'name with spaces and a target with brackets' => ['my link', ['my [target].txt', FALSE]];
    yield 'name that prefixes another name' => ['link', NULL];
    yield 'junction' => ['junction', NULL];
    yield 'name not listed' => ['missing', NULL];
  }

  public function testCopyUnreadableSymlink(): void {
    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $this->createFiles($source_dir, ['file.txt' => 'file']);
    $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . 'link.txt', 'file.txt');

    $file = new class() extends File {

      /**
       * {@inheritdoc}
       */
      protected static function readSymlinkTarget(string $link, ?string $root): string|false {
        return FALSE;
      }

    };

    $this->expectException(FileException::class);
    $this->expectExceptionMessage(sprintf('Unable to read the target of symlink "%s/link.txt".', $source_dir));

    $file::copy($source_dir, $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination');
  }

  public function testCopySymlinkOverExistingFile(): void {
    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';

    $this->createFiles($source_dir, ['file.txt' => 'file']);
    $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . 'link.txt', 'file.txt');
    File::dump($dest_dir . DIRECTORY_SEPARATOR . 'link.txt', 'existing');

    $this->assertTrue(File::copy($source_dir, $dest_dir));

    $this->assertFalse(is_link($dest_dir . DIRECTORY_SEPARATOR . 'link.txt'));
    $this->assertSame('existing', file_get_contents($dest_dir . DIRECTORY_SEPARATOR . 'link.txt'));
  }

  public function testSetSymlinkModeUnknown(): void {
    $this->expectException(FileException::class);
    $this->expectExceptionMessage('Unknown symlink mode "absolute".');

    File::setSymlinkMode('absolute');
  }

  #[DataProvider('dataProviderCopySymlinkModePerCall')]
  public function testCopySymlinkModePerCall(string $global_mode, ?string $call_mode, bool $expect_relative): void {
    File::setSymlinkMode($global_mode);

    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';
    $this->createFiles($source_dir, ['target.txt' => 'target']);

    // An absolute target points into the source in the native mode, on every
    // platform.
    $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . 'link.txt', realpath($source_dir) . DIRECTORY_SEPARATOR . 'target.txt');

    $this->assertTrue(File::copy($source_dir, $dest_dir, symlink_mode: $call_mode));
    $this->assertSame($global_mode, File::getSymlinkMode(), 'The global symlink mode is unchanged.');

    // The relative copy reads the copied target, and the native copy reads
    // the source target.
    File::dump($source_dir . DIRECTORY_SEPARATOR . 'target.txt', 'changed');
    $this->assertSame($expect_relative ? 'target' : 'changed', file_get_contents($dest_dir . DIRECTORY_SEPARATOR . 'link.txt'));
  }

  public static function dataProviderCopySymlinkModePerCall(): \Iterator {
    yield 'global native, call relative' => [File::SYMLINK_MODE_NATIVE, File::SYMLINK_MODE_RELATIVE, TRUE];
    yield 'global relative, call native' => [File::SYMLINK_MODE_RELATIVE, File::SYMLINK_MODE_NATIVE, FALSE];
    yield 'global native, no call mode' => [File::SYMLINK_MODE_NATIVE, NULL, FALSE];
    yield 'global relative, no call mode' => [File::SYMLINK_MODE_RELATIVE, NULL, TRUE];
  }

  public function testCopySymlinkModePerCallWhenCopyThrows(): void {
    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $this->createFiles($source_dir, ['file.txt' => 'file']);
    $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . 'link.txt', 'file.txt');

    $file = new class() extends File {

      /**
       * {@inheritdoc}
       */
      protected static function readSymlinkTarget(string $link, ?string $root): string|false {
        return FALSE;
      }

    };

    try {
      $file::copy($source_dir, $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination', symlink_mode: File::SYMLINK_MODE_RELATIVE);
      $this->fail('The copy throws.');
    }
    catch (FileException) {
      $this->assertSame(File::SYMLINK_MODE_NATIVE, File::getSymlinkMode());
    }
  }

  public function testCopyFilterCallableArray(): void {
    $this->createFiles($this->testTmpDir . DIRECTORY_SEPARATOR . 'source', ['keep.txt' => 'keep', 'skip.txt' => 'skip']);

    // An array that is a callable is used as a callback, not as a path list.
    $filter = [
      new class() {

        /**
         * Select keep.txt.
         */
        public function select(string $path): bool {
          return $path === 'keep.txt';
        }

      },
      'select',
    ];

    $this->assertTrue(File::copy($this->testTmpDir . DIRECTORY_SEPARATOR . 'source', $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination', filter: $filter));
    $this->assertFileExists($this->testTmpDir . DIRECTORY_SEPARATOR . self::nativePath('destination/keep.txt'));
    $this->assertFileDoesNotExist($this->testTmpDir . DIRECTORY_SEPARATOR . self::nativePath('destination/skip.txt'));
  }

  #[DataProvider('dataProviderCopyFilterPathList')]
  public function testCopyFilterPathList(array $files, array $paths, array $expected): void {
    $source = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';
    $this->createFiles($source, $files);

    $this->assertTrue(File::copy($source, $dest, filter: $paths));
    $this->assertSame(array_map(self::nativePath(...), $expected), $this->listTree($dest));
  }

  public static function dataProviderCopyFilterPathList(): \Iterator {
    $files = ['hooks/run.sh' => 'run', 'hooks/other.sh' => 'other', 'library/a.txt' => 'a', 'library/sub/b.txt' => 'b', 'top.txt' => 'top'];

    yield 'file' => [$files, ['hooks/run.sh'], ['hooks/run.sh']];
    yield 'directory with its subtree' => [$files, ['library'], ['library/a.txt', 'library/sub/b.txt']];
    yield 'leading dot segment' => [$files, ['./hooks/run.sh'], ['hooks/run.sh']];
    yield 'dot segment inside' => [$files, ['hooks/./run.sh'], ['hooks/run.sh']];
    yield 'repeated separators' => [$files, ['hooks//run.sh'], ['hooks/run.sh']];
    yield 'leading and trailing separators' => [$files, ['/library/'], ['library/a.txt', 'library/sub/b.txt']];
    yield 'backslash separator' => [$files, ['hooks\\run.sh', 'top.txt'], PHP_OS_FAMILY === 'Windows' ? ['hooks/run.sh', 'top.txt'] : ['top.txt']];
    yield 'ancestor is a file' => [['a' => 'file', 'top.txt' => 'top'], ['a/b.txt', 'top.txt'], ['top.txt']];
  }

  public function testCopyFilterPathNotString(): void {
    $this->createFiles($this->testTmpDir . DIRECTORY_SEPARATOR . 'source', ['file.txt' => 'file']);

    $this->expectException(FileException::class);
    $this->expectExceptionMessage('The copy() filter path list holds a int instead of a path.');

    // @phpstan-ignore-next-line
    File::copy($this->testTmpDir . DIRECTORY_SEPARATOR . 'source', $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination', filter: ['file.txt', 1]);
  }

  public function testCopySymlinkModeUnknown(): void {
    $this->createFiles($this->testTmpDir . DIRECTORY_SEPARATOR . 'source', ['file.txt' => 'file']);

    $this->expectException(FileException::class);
    $this->expectExceptionMessage('Unknown symlink mode "absolute".');

    File::copy($this->testTmpDir . DIRECTORY_SEPARATOR . 'source', $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination', symlink_mode: 'absolute');
  }

  #[DataProvider('dataProviderSymlinkHandling')]
  public function testSymlinkHandling(bool $is_dir, bool $absolute, string $target, string $link, bool $chained, bool $dangling): void {
    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    $target_path = $tree . DIRECTORY_SEPARATOR . self::nativePath($target);
    $link_path = $tree . DIRECTORY_SEPARATOR . self::nativePath($link);
    $hop_path = dirname($link_path) . DIRECTORY_SEPARATOR . 'hop';

    // A target outside the tree is reached through the tree directory.
    File::mkdir($tree);
    $this->createTarget($target_path, $is_dir, $target);
    $target_path = realpath(dirname($target_path)) . DIRECTORY_SEPARATOR . basename($target_path);

    if ($chained) {
      $this->createSymlink($hop_path, $this->symlinkTargetString($target_path, $hop_path, $absolute));
      $this->createSymlink($link_path, $this->symlinkTargetString($hop_path, $link_path, $absolute));
    }
    else {
      $this->createSymlink($link_path, $this->symlinkTargetString($target_path, $link_path, $absolute));
    }

    if ($dangling) {
      File::remove($target_path);
    }

    // realpath() follows the chain to the final target, missing or not.
    $this->assertSame($target_path, File::realpath($link_path));

    // A dangling symlink does not exist.
    $this->assertSame(!$dangling, File::exists($link_path));

    // scandir() lists every symlink and does not walk a directory symlink.
    $listed = $this->listScandir($tree);
    $this->assertContains($link, $listed);

    if ($chained) {
      $this->assertContains(ltrim(dirname($link) . '/hop', './'), $listed);
    }

    $this->assertNotContains($link . '/inner.txt', $listed);

    // remove() removes the symlink and leaves its target.
    File::remove($link_path);
    $this->assertNotContains(basename($link_path), (array) scandir(dirname($link_path)));
    $this->assertSame(!$dangling, file_exists($target_path));

    // remove() removes a tree that still holds symlinks, dangling or not.
    File::remove($tree);
    $this->assertDirectoryDoesNotExist($tree);
  }

  public static function dataProviderSymlinkHandling(): \Iterator {
    $locations = [
      'same directory' => ['a/b/target', 'a/b/link'],
      'parent' => ['a/target', 'a/b/link'],
      'child' => ['a/b/c/target', 'a/b/link'],
      'link at the tree root' => ['a/b/target', 'link'],
      'outside the tree' => ['../outside/target', 'a/b/link'],
    ];

    foreach (['file' => FALSE, 'directory' => TRUE] as $kind => $is_dir) {
      foreach (['relative' => FALSE, 'absolute' => TRUE] as $style => $absolute) {
        foreach ($locations as $location => [$target, $link]) {
          foreach (['direct' => FALSE, 'through a second link' => TRUE] as $shape => $chained) {
            foreach (['target present' => FALSE, 'dangling' => TRUE] as $state => $dangling) {
              yield sprintf('%s link, %s target, %s, %s, %s', $kind, $style, $location, $shape, $state) => [$is_dir, $absolute, $target, $link, $chained, $dangling];
            }
          }
        }
      }
    }
  }

  public function testSymlinkLoop(): void {
    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    File::mkdir($tree);
    $this->createDanglingSymlink($tree . DIRECTORY_SEPARATOR . 'a', 'b');
    $this->createDanglingSymlink($tree . DIRECTORY_SEPARATOR . 'b', 'a');

    $this->assertFalse(File::exists($tree . DIRECTORY_SEPARATOR . 'a'));
    $this->assertSame(['a', 'b'], $this->listScandir($tree));
    $this->assertFalse(File::dirIsEmpty($tree));

    try {
      File::realpath($tree . DIRECTORY_SEPARATOR . 'a');
      $this->fail('A symlink loop throws.');
    }
    catch (FileException $file_exception) {
      $this->assertStringContainsString(DIRECTORY_SEPARATOR . 'a": the symlinks form a loop.', $file_exception->getMessage());
    }

    File::remove($tree);
    $this->assertDirectoryDoesNotExist($tree);
  }

  public function testScandirSymlinksToAncestors(): void {
    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    $this->createFiles($tree, ['a/file.txt' => 'file']);
    $this->createSymlink($tree . DIRECTORY_SEPARATOR . self::nativePath('a/self'), '.');
    $this->createSymlink($tree . DIRECTORY_SEPARATOR . self::nativePath('a/up'), '..');

    $this->assertSame(['a', 'a/file.txt', 'a/self', 'a/up'], $this->listScandir($tree));
  }

  public function testRmdirIfEmptyKeepsDirectorySymlink(): void {
    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    File::mkdir($tree . DIRECTORY_SEPARATOR . 'empty');
    $this->createSymlink($tree . DIRECTORY_SEPARATOR . 'link', 'empty');

    File::rmdirIfEmpty($tree . DIRECTORY_SEPARATOR . 'link');

    $this->assertTrue(is_link($tree . DIRECTORY_SEPARATOR . 'link'));
    $this->assertDirectoryExists($tree . DIRECTORY_SEPARATOR . 'empty');
  }

  public function testSymlinkResolvesLoop(): void {
    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    File::mkdir($tree);
    $this->createDanglingSymlink($tree . DIRECTORY_SEPARATOR . 'a', 'b');
    $this->createDanglingSymlink($tree . DIRECTORY_SEPARATOR . 'b', 'a');

    $this->assertFalse(self::callProtectedMethod(File::class, 'symlinkResolves', [$tree . DIRECTORY_SEPARATOR . 'a']));
  }

  public function testFileSymlinkToDirectoryHandling(): void {
    // Only Windows has file symlinks to directories.
    if (DIRECTORY_SEPARATOR !== '\\') {
      $this->markTestSkipped('Windows only.');
    }

    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    $this->createFiles($tree, ['update/script.sh' => 'script']);
    exec(sprintf('cmd /c mklink %s update', escapeshellarg($tree . '\\deploy')), $output, $exit_code);
    $this->assertSame(0, $exit_code, implode(PHP_EOL, $output));

    $this->assertSame(realpath($tree . '\\update'), File::realpath($tree . '\\deploy'));
    $this->assertTrue(File::exists($tree . '\\deploy'));
    $this->assertSame(['deploy', 'update', 'update/script.sh'], $this->listScandir($tree));

    File::remove($tree . '\\deploy');
    $this->assertNotContains('deploy', (array) scandir($tree));
    $this->assertFileExists($tree . '\\update\\script.sh');
  }

  #[DataProvider('dataProviderReadSymlink')]
  public function testReadSymlink(bool $outside, bool $absolute, bool $dangling, bool $with_root): void {
    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    $target = $outside ? $this->testTmpDir . DIRECTORY_SEPARATOR . self::nativePath('outside/target.txt') : $tree . DIRECTORY_SEPARATOR . self::nativePath('library/target.txt');
    $link = $tree . DIRECTORY_SEPARATOR . self::nativePath('hooks/link');

    File::dump($target, 'target');
    $target = realpath(dirname($target)) . DIRECTORY_SEPARATOR . basename($target);
    $stored = $absolute ? $target : self::nativePath($outside ? '../../outside/target.txt' : '../library/target.txt');
    $this->createSymlink($link, $stored);

    if ($dangling) {
      File::remove($target);
    }

    // The global mode does not change the result.
    File::setSymlinkMode(File::SYMLINK_MODE_NATIVE);

    // On Windows, $tree uses the 8.3 short form of the temporary directory,
    // which the long form of the target is compared with.
    $result = File::readSymlink($link, $with_root ? $tree : NULL);

    if ($with_root) {
      $this->assertSame($outside ? $target : '../library/target.txt', $result);
    }
    elseif (DIRECTORY_SEPARATOR === '/' || $dangling) {
      $this->assertSame($stored, $result);
    }
    else {
      // readlink() on Windows returns the resolved target.
      $this->assertIsString($result);
      $this->assertSame(realpath($target), realpath($result));
    }
  }

  public static function dataProviderReadSymlink(): \Iterator {
    foreach (['inside the root' => FALSE, 'outside the root' => TRUE] as $location => $outside) {
      foreach (['relative' => FALSE, 'absolute' => TRUE] as $style => $absolute) {
        foreach (['present' => FALSE, 'dangling' => TRUE] as $state => $dangling) {
          foreach (['with a root' => TRUE, 'without a root' => FALSE] as $root => $with_root) {
            yield sprintf('%s, %s target, %s, %s', $location, $style, $state, $root) => [$outside, $absolute, $dangling, $with_root];
          }
        }
      }
    }
  }

  public function testReadSymlinkRootInDifferentCase(): void {
    if (DIRECTORY_SEPARATOR !== '\\') {
      $this->markTestSkipped('Windows only.');
    }

    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    File::dump($tree . '\\library\\target.txt', 'target');
    $this->createSymlink($tree . '\\hooks\\link', '..\\library\\target.txt');

    $this->assertSame('../library/target.txt', File::readSymlink($tree . '\\hooks\\link', strtoupper($tree)));
  }

  public function testReadSymlinkMissing(): void {
    $this->assertFalse(File::readSymlink($this->testTmpDir . DIRECTORY_SEPARATOR . 'missing', $this->testTmpDir));
  }

  public function testReadSymlinkNotSymlink(): void {
    $this->createFiles($this->testTmpDir, ['dir/file.txt' => 'file']);

    $this->assertFalse(File::readSymlink($this->testTmpDir . DIRECTORY_SEPARATOR . self::nativePath('dir/file.txt')));
    $this->assertFalse(File::readSymlink($this->testTmpDir . DIRECTORY_SEPARATOR . 'dir', $this->testTmpDir));
  }

  #[DataProvider('dataProviderRealpathPosixTargetCharacters')]
  public function testRealpathPosixTargetCharacters(string $target): void {
    if (DIRECTORY_SEPARATOR === '\\') {
      $this->markTestSkipped('A backslash and a colon are filename characters only outside Windows.');
    }

    $link = $this->testTmpDir . '/tree/link';
    File::mkdir(dirname($link));
    symlink($target, $link);

    // The target is relative, so it resolves inside the symlink directory.
    $this->assertSame(realpath($this->testTmpDir . '/tree') . '/' . $target, File::realpath($link));
  }

  public static function dataProviderRealpathPosixTargetCharacters(): \Iterator {
    yield 'leading backslash' => ['\\missing'];
    yield 'drive-like prefix' => ['a:b'];
  }

  public function testDirectoryOperationsSkipDirectorySymlink(): void {
    $tree = $this->testTmpDir . DIRECTORY_SEPARATOR . 'tree';
    $this->createFiles($tree, ['dir/file.txt' => 'needle']);
    $this->createSymlink($tree . DIRECTORY_SEPARATOR . 'dir_link', 'dir');
    $this->createSymlink($tree . DIRECTORY_SEPARATOR . 'file_link.txt', self::nativePath('dir/file.txt'));

    // A directory symlink is listed only with the directories.
    $this->assertSame(['dir/file.txt', 'file_link.txt'], $this->listScandir($tree, FALSE));

    $found = array_map(basename(...), File::findContainingInDir($tree, 'needle'));
    sort($found);
    $this->assertSame(['file.txt', 'file_link.txt'], $found);

    File::replaceContentInDir($tree, 'needle', 'replaced');
    File::removeLineInDir($tree, 'missing');
    File::collapseEmptyLinesInDir($tree);
    $this->assertSame('replaced', file_get_contents($tree . DIRECTORY_SEPARATOR . self::nativePath('dir/file.txt')));
  }

  public function testCopyDanglingSymlinkWithShellCharacters(): void {
    File::setSymlinkMode(File::SYMLINK_MODE_RELATIVE);

    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';
    $target = 'ta!rget%PATH%.txt';
    $link = 'li!nk%PATH%';

    // The target exists while the symlink is created, so PHP on Windows
    // accepts it, then it is removed to leave the symlink dangling.
    File::dump($source_dir . DIRECTORY_SEPARATOR . $target, 'source');
    $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . $link, $target);
    File::remove($source_dir . DIRECTORY_SEPARATOR . $target);

    $this->assertTrue(File::copy($source_dir, $dest_dir));

    // The restored target is read through the copied symlink, so both names
    // are exact.
    File::dump($dest_dir . DIRECTORY_SEPARATOR . $target, 'restored');
    $this->assertSame('restored', file_get_contents($dest_dir . DIRECTORY_SEPARATOR . $link));
  }

  #[DataProvider('dataProviderIsPathInside')]
  public function testIsPathInside(string $path, string $dir, ?bool $ignore_case, bool $expected): void {
    $this->assertSame($expected, File::isPathInside($path, $dir, $ignore_case));
  }

  public static function dataProviderIsPathInside(): \Iterator {
    yield 'same directory' => ['/x/src', '/x/src', NULL, TRUE];
    yield 'nested path' => ['/x/src/a/b', '/x/src', NULL, TRUE];
    yield 'directory with a trailing slash' => ['/x/src/a', '/x/src/', NULL, TRUE];
    yield 'directory sharing the prefix' => ['/x/src2/a', '/x/src', NULL, FALSE];
    yield 'parent directory' => ['/x', '/x/src', NULL, FALSE];
    yield 'different case, ignoring case' => ['/x/SRC/a', '/x/src', TRUE, TRUE];
    yield 'different case, matching case' => ['/x/SRC/a', '/x/src', FALSE, FALSE];
    yield 'parent segment that leaves the directory' => ['/x/src/../outside/file', '/x/src', NULL, FALSE];
    yield 'parent segment that stays inside the directory' => ['/x/src/a/../b', '/x/src', NULL, TRUE];
    yield 'parent segment in the directory' => ['/x/src/a', '/x/other/../src', NULL, TRUE];
    yield 'root directory' => ['/x/src', '/', NULL, TRUE];
  }

  #[DataProvider('dataProviderToForwardSlashes')]
  public function testToForwardSlashes(string $path, string $expected): void {
    $this->assertSame($expected, File::toForwardSlashes($path));
  }

  public static function dataProviderToForwardSlashes(): \Iterator {
    yield 'forward slashes' => ['a/b/c', 'a/b/c'];
    // A backslash is a filename character outside Windows.
    yield 'backslashes' => ['a\\b/c', DIRECTORY_SEPARATOR === '\\' ? 'a/b/c' : 'a\\b/c'];
  }

  #[DataProvider('dataProviderNormalizePath')]
  public function testNormalizePath(string $path, string $expected): void {
    $this->assertSame(self::nativePath($expected), self::callProtectedMethod(File::class, 'normalizePath', [$path]));
  }

  public static function dataProviderNormalizePath(): \Iterator {
    yield 'parent segment' => ['/x/a/../b', '/x/b'];
    yield 'current segment and repeated separators' => ['/x/./a//b/', '/x/a/b'];
    yield 'parent segment above the root' => ['/x/../../b', '/b'];
    yield 'root' => ['/', '/'];
    // A backslash separates segments only on Windows.
    yield 'backslash in a name' => ['/x/miss\\ing/file', DIRECTORY_SEPARATOR === '\\' ? '/x/miss/ing/file' : '/x/miss\\ing/file'];
    // A UNC root keeps both leading separators, and ".." cannot leave it.
    yield 'UNC root' => ['\\\\server\\share\\a\\..\\..\\file.txt', DIRECTORY_SEPARATOR === '\\' ? '\\\\server\\share\\file.txt' : '\\\\server\\share\\a\\..\\..\\file.txt'];
    // A colon starts a drive only on Windows.
    yield 'drive letter' => ['C:/x/../file.txt', DIRECTORY_SEPARATOR === '\\' ? 'C:\\file.txt' : 'C:/file.txt'];
  }

  public function testResolveMissingPath(): void {
    File::mkdir($this->testTmpDir . DIRECTORY_SEPARATOR . 'existing');
    $path = $this->testTmpDir . DIRECTORY_SEPARATOR . self::nativePath('existing/missing/../gone/file.txt');

    // The existing ancestor resolves through symlinks, such as /var on macOS.
    $expected = realpath($this->testTmpDir . DIRECTORY_SEPARATOR . 'existing') . DIRECTORY_SEPARATOR . self::nativePath('gone/file.txt');
    $this->assertSame($expected, self::callProtectedMethod(File::class, 'resolveMissingPath', [$path]));
  }

  public function testCopyDanglingSymlinkWithBackslash(): void {
    if (DIRECTORY_SEPARATOR === '\\') {
      $this->markTestSkipped('A backslash is a directory separator on Windows.');
    }

    File::setSymlinkMode(File::SYMLINK_MODE_RELATIVE);

    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';
    File::mkdir($source_dir . '/a');
    symlink('miss\\ing/file.txt', $source_dir . '/a/link');

    $this->assertTrue(File::copy($source_dir, $dest_dir));
    $this->assertSame('miss\\ing/file.txt', readlink($dest_dir . '/a/link'));
  }

  /**
   * Create files with content under a directory.
   *
   * @param string $dir
   *   Directory to create the files in.
   * @param array<string, string> $files
   *   File content keyed by path relative to $dir.
   */
  protected function createFiles(string $dir, array $files): void {
    foreach ($files as $path => $content) {
      File::dump($dir . DIRECTORY_SEPARATOR . self::nativePath($path), $content);
    }
  }

  /**
   * Create a symlink from within its own directory.
   *
   * PHP on Windows resolves a relative target against the current directory
   * and refuses to create a symlink to a missing target.
   *
   * @param string $link
   *   Symlink path.
   * @param string $target
   *   Symlink target.
   */
  protected function createSymlink(string $link, string $target): void {
    $current_dir = getcwd();
    $this->assertIsString($current_dir, 'Failed to get current working directory');

    File::mkdir(dirname($link));
    chdir(dirname($link));
    $created = symlink($target, basename($link));
    chdir($current_dir);

    $this->assertTrue($created, sprintf('Created symlink %s -> %s', $link, $target));
  }

  /**
   * Create a file symlink to a target that does not exist.
   *
   * PHP on Windows refuses to create a symlink to a missing target, so the
   * Windows mklink command creates it there.
   *
   * @param string $link
   *   Symlink path.
   * @param string $target
   *   Symlink target, relative to the symlink directory.
   */
  protected function createDanglingSymlink(string $link, string $target): void {
    if (DIRECTORY_SEPARATOR === '\\') {
      exec(sprintf('mklink %s %s 2>&1', escapeshellarg($link), escapeshellarg($target)), $output, $exit_code);
      $this->assertSame(0, $exit_code, implode(PHP_EOL, $output));

      return;
    }

    $this->assertTrue(symlink($target, $link), sprintf('Created symlink %s -> %s', $link, $target));
  }

  /**
   * List the paths File::scandir() discovers, relative to the directory.
   *
   * @param string $dir
   *   Directory to scan.
   * @param bool $include_dirs
   *   Whether to include directories in the result.
   *
   * @return array<int, string>
   *   Sorted paths relative to $dir with "/" separators.
   */
  protected function listScandir(string $dir, bool $include_dirs = TRUE): array {
    $base = File::toForwardSlashes(File::dir($dir)) . '/';
    $paths = array_map(static fn(string $path): string => substr(File::toForwardSlashes($path), strlen($base)), File::scandir($dir, [], $include_dirs));
    sort($paths);

    return $paths;
  }

  /**
   * Create a symlink target as a file, or a directory holding inner.txt.
   *
   * @param string $path
   *   Target path.
   * @param bool $is_dir
   *   Whether the target is a directory.
   * @param string $content
   *   Content of the file or of inner.txt.
   */
  protected function createTarget(string $path, bool $is_dir, string $content): void {
    File::dump($is_dir ? $path . DIRECTORY_SEPARATOR . 'inner.txt' : $path, $content);
  }

  /**
   * Build the target string for a symlink to an existing path.
   *
   * @param string $target
   *   Existing target path.
   * @param string $link
   *   Symlink path.
   * @param bool $absolute
   *   Whether the target string is absolute.
   *
   * @return string
   *   Absolute target, or the target relative to the symlink directory.
   */
  protected function symlinkTargetString(string $target, string $link, bool $absolute): string {
    File::mkdir(dirname($link));
    $target = realpath(dirname($target)) . DIRECTORY_SEPARATOR . basename($target);

    if ($absolute) {
      return $target;
    }

    return self::nativePath(rtrim((new Filesystem())->makePathRelative($target, (string) realpath(dirname($link))), '/'));
  }

  /**
   * List the files and symlinks in a tree without following symlinks.
   *
   * @param string $dir
   *   Tree root.
   *
   * @return array<int, string>
   *   Sorted paths relative to $dir.
   */
  protected function listTree(string $dir): array {
    $paths = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
      if ($file instanceof \SplFileInfo) {
        $paths[] = substr($file->getPathname(), strlen($dir) + 1);
      }
    }

    sort($paths);

    return $paths;
  }

  /**
   * Convert forward slashes to the platform directory separator.
   *
   * Windows does not resolve a relative symlink target with forward slashes.
   */
  protected static function nativePath(string $path): string {
    return str_replace('/', DIRECTORY_SEPARATOR, $path);
  }

  public function testScandirRecursiveEmptyDirectory(): void {
    // Create directory and make it unreadable to simulate empty scandir.
    $empty_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'empty_dir_test';
    mkdir($empty_dir, 0777);

    $removed_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'removed_dir';
    mkdir($removed_dir, 0777);
    rmdir($removed_dir);

    $files = File::scandir($removed_dir);
    $this->assertEmpty($files);
  }

  public function testScandirRecursiveActuallyEmptyDirectory(): void {
    $empty_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'truly_empty_dir';
    mkdir($empty_dir, 0777);

    $this->assertDirectoryExists($empty_dir);

    $files = File::scandir($empty_dir);
    $this->assertEmpty($files);

    rmdir($empty_dir);
  }

  #[DataProvider('dataProviderAppend')]
  public function testAppend(string $initial_content, string $append_content, string $expected_content): void {
    $test_file = $this->testTmpDir . DIRECTORY_SEPARATOR . 'append_test.txt';

    file_put_contents($test_file, $initial_content);
    $this->assertFileExists($test_file);

    File::append($test_file, $append_content);

    $actual_content = file_get_contents($test_file);
    $this->assertIsString($actual_content, 'Failed to read file content');
    $this->assertSame($expected_content, $actual_content);
  }

  public static function dataProviderAppend(): \Iterator {
    yield 'append to empty file' => ['', 'Hello World', 'Hello World'];
    yield 'append to existing content' => ['Hello', ' World', 'Hello World'];
    yield 'append multiline content' => ["First line\n", "Second line\n", "First line\nSecond line\n"];
    yield 'append empty string' => ['Existing content', '', 'Existing content'];
    yield 'append special characters' => ['Base: ', '«Special»', 'Base: «Special»'];
    yield 'append with newlines' => ['Line 1', "\nLine 2\nLine 3", "Line 1\nLine 2\nLine 3"];
  }

  public function testAppendThrowsExceptionForNonExistentFile(): void {
    $non_existent_file = $this->testTmpDir . DIRECTORY_SEPARATOR . 'non_existent.txt';

    $this->expectException(FileException::class);
    $this->expectExceptionMessage(sprintf('File "%s" does not exist.', $non_existent_file));

    File::append($non_existent_file, 'content');
  }

  public function testRemoveSingleFile(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'remove_test.txt';
    file_put_contents($file_path, 'content to remove');
    $this->assertFileExists($file_path);

    File::remove($file_path);

    $this->assertFileDoesNotExist($file_path);
  }

  public function testRemoveDirectory(): void {
    $dir_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'remove_dir';
    mkdir($dir_path, 0777);
    file_put_contents($dir_path . DIRECTORY_SEPARATOR . 'file.txt', 'content');
    $this->assertDirectoryExists($dir_path);

    File::remove($dir_path);

    $this->assertDirectoryDoesNotExist($dir_path);
  }

  public function testRemoveIterable(): void {
    $file1 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'remove1.txt';
    $file2 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'remove2.txt';
    $file3 = $this->testTmpDir . DIRECTORY_SEPARATOR . 'remove3.txt';

    file_put_contents($file1, 'content1');
    file_put_contents($file2, 'content2');
    file_put_contents($file3, 'content3');

    $this->assertFileExists($file1);
    $this->assertFileExists($file2);
    $this->assertFileExists($file3);

    File::remove([$file1, $file2, $file3]);

    $this->assertFileDoesNotExist($file1);
    $this->assertFileDoesNotExist($file2);
    $this->assertFileDoesNotExist($file3);
  }

  public function testRemoveNonExistentFile(): void {
    $non_existent = $this->testTmpDir . DIRECTORY_SEPARATOR . 'does_not_exist.txt';
    $this->assertFileDoesNotExist($non_existent);

    // File::remove() does not throw for a missing path.
    File::remove($non_existent);

    $this->assertFileDoesNotExist($non_existent);
  }

  #[DataProvider('dataProviderRemove')]
  public function testRemove(string $type, string $name): void {
    $path = $this->testTmpDir . DIRECTORY_SEPARATOR . $name;

    if ($type === 'file') {
      file_put_contents($path, 'test content');
      $this->assertFileExists($path);
    }
    elseif ($type === 'directory') {
      mkdir($path, 0777);
      $this->assertDirectoryExists($path);
    }
    elseif ($type === 'nested_directory') {
      mkdir($path, 0777);
      mkdir($path . DIRECTORY_SEPARATOR . 'subdir', 0777);
      file_put_contents($path . DIRECTORY_SEPARATOR . 'file.txt', 'content');
      file_put_contents($path . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'nested.txt', 'nested');
      $this->assertDirectoryExists($path);
    }

    File::remove($path);

    $this->assertFileDoesNotExist($path);
    $this->assertDirectoryDoesNotExist($path);
  }

  public static function dataProviderRemove(): \Iterator {
    yield 'single file' => ['file', 'single.txt'];
    yield 'empty directory' => ['directory', 'empty_dir'];
    yield 'nested directory with files' => ['nested_directory', 'nested_dir'];
    yield 'file with special chars' => ['file', 'special-file_name.txt'];
    yield 'hidden file' => ['file', '.hidden_file'];
  }

  /**
   * Convert a rooted POSIX path to the form File::realpath() returns.
   *
   * The root keeps its forward slash and the remaining separators become
   * DIRECTORY_SEPARATOR.
   */
  protected static function rooted(string $path): string {
    return '/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
  }

}
