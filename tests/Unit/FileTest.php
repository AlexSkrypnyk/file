<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;

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

  public function testCopyPathsWithQueuedSymlinks(): void {
    $source_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'destination';

    $this->createFiles($source_dir, [
      'library/provision.sh' => 'provision',
      'hooks/update/script.sh' => 'script',
    ]);
    $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . self::nativePath('hooks/1.provision.sh'), self::nativePath('../library/provision.sh'));
    $this->createSymlink($source_dir . DIRECTORY_SEPARATOR . self::nativePath('hooks/deploy'), 'update');

    // Symlinks come before their targets.
    $symlinks = [];
    foreach (['hooks/1.provision.sh', 'hooks/deploy', 'hooks/update/script.sh', 'library/provision.sh'] as $path) {
      $path = self::nativePath($path);
      $this->assertTrue(File::copy($source_dir . DIRECTORY_SEPARATOR . $path, $dest_dir . DIRECTORY_SEPARATOR . $path, root: $source_dir, symlinks: $symlinks));
    }

    $this->assertCount(2, $symlinks);
    $this->assertFalse(is_link($dest_dir . DIRECTORY_SEPARATOR . self::nativePath('hooks/1.provision.sh')), 'Queued symlink is not created yet.');

    $this->assertTrue(File::createSymlinks($symlinks));
    File::remove($source_dir);

    $this->assertSame('provision', file_get_contents($dest_dir . DIRECTORY_SEPARATOR . self::nativePath('hooks/1.provision.sh')));
    $this->assertSame('script', file_get_contents($dest_dir . DIRECTORY_SEPARATOR . self::nativePath('hooks/deploy/script.sh')));

    // readlink() returns the resolved target on Windows.
    if (DIRECTORY_SEPARATOR === '/') {
      $this->assertSame('../library/provision.sh', readlink($dest_dir . '/hooks/1.provision.sh'));
      $this->assertSame('update', readlink($dest_dir . '/hooks/deploy'));
    }
  }

  public function testCopyFileSymlinkToDirectory(): void {
    // Only Windows has file symlinks to directories, and readlink() fails on
    // them. Archive extractors create one when the target is not extracted
    // yet.
    if (DIRECTORY_SEPARATOR !== '\\') {
      $this->markTestSkipped('Windows only.');
    }

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

    $result = File::copy($source_dir . DIRECTORY_SEPARATOR . 'link.txt', $dest_dir . DIRECTORY_SEPARATOR . 'link.txt');
    chmod($dest_dir, 0755);

    $this->assertFalse($result);
    $this->assertFalse(is_link($dest_dir . DIRECTORY_SEPARATOR . 'link.txt'));
  }

  #[DataProvider('dataProviderRelativeSymlinkTarget')]
  public function testRelativeSymlinkTarget(string $target, string $link_dir, string $root, string $expected): void {
    $this->assertSame($expected, self::callProtectedMethod(File::class, 'relativeSymlinkTarget', [$target, $link_dir, $root]));
  }

  public static function dataProviderRelativeSymlinkTarget(): \Iterator {
    yield 'file in a sibling directory' => ['/x/src/library/provision.sh', '/x/src/hooks', '/x/src', '../library/provision.sh'];
    yield 'file in the same directory' => ['/x/src/hooks/run.sh', '/x/src/hooks', '/x/src', 'run.sh'];
    yield 'file in a nested directory' => ['/x/src/hooks/a/b/run.sh', '/x/src/hooks', '/x/src', 'a/b/run.sh'];
    yield 'directory the symlink is in' => ['/x/src/hooks', '/x/src/hooks', '/x/src', '.'];
    yield 'root directory' => ['/x/src', '/x/src/hooks', '/x/src', '..'];
    yield 'root with a trailing slash' => ['/x/src/hooks/run.sh', '/x/src/hooks', '/x/src/', 'run.sh'];
    yield 'root in a different case' => ['/x/src/hooks/run.sh', '/x/src/hooks', '/X/SRC', 'run.sh'];
    yield 'shared directory in a different case' => ['/x/SRC/library/provision.sh', '/x/src/hooks', '/x/src', '../library/provision.sh'];
    yield 'file outside the root' => ['/x/other/run.sh', '/x/src/hooks', '/x/src', '/x/other/run.sh'];
    yield 'root parent directory' => ['/x', '/x/src/hooks', '/x/src', '/x'];
    yield 'directory sharing the root prefix' => ['/x/src2/run.sh', '/x/src/hooks', '/x/src', '/x/src2/run.sh'];
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
