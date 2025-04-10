<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(File::class)]
class FileStringsTest extends UnitTestBase {

  public function testCopy(): void {
    static::$fixtures = $this->locationsFixtureDir();
    File::copy(static::$fixtures, static::$sut);

    $dir = static::$sut . DIRECTORY_SEPARATOR;

    $this->assertTrue(is_file($dir . 'file.txt'));
    $this->assertTrue((fileperms($dir . 'file.txt') & 0777) === 0755);
    $this->assertTrue(is_dir($dir . 'dir'));
    $this->assertTrue(is_file($dir . 'dir/file_in_dir.txt'));
    $this->assertTrue(is_dir($dir . 'dir/subdir'));
    $this->assertTrue(is_file($dir . 'dir/subdir/file_in_subdir.txt'));

    $this->assertTrue(is_link($dir . 'file_link.txt'));

    $this->assertTrue(is_link($dir . 'dir_link'));
    $this->assertTrue(is_dir($dir . 'dir_link/subdir'));
    $this->assertTrue(is_file($dir . 'dir_link/subdir/file_in_subdir.txt'));
    $this->assertTrue(is_link($dir . 'dir_link/subdir/file_link_from_subdir.txt'));

    $this->assertTrue(is_link($dir . 'subdir_link_root'));
    $this->assertTrue(is_link($dir . 'subdir_link_root/file_link_from_subdir.txt'));
    $this->assertTrue((fileperms($dir . 'subdir_link_root/file_link_from_subdir.txt') & 0777) === 0755);
    $this->assertTrue(is_file($dir . 'subdir_link_root/file_in_subdir.txt'));

    $this->assertTrue(is_link($dir . 'dir/subdir_link'));
    $this->assertTrue(is_dir($dir . 'dir/subdir_link'));

    $this->assertDirectoryDoesNotExist($dir . 'emptydir');
  }

  #[DataProvider('dataProviderContains')]
  public function testContains(string $string, string $file, mixed $expected): void {
    $dir = $this->locationsFixtureDir('tokens');

    $files = $this->flattenFileTree([$file], $dir);
    $created_files = static::locationsCopyFilesToSut($files, $dir);
    $created_file = reset($created_files);

    if (empty($created_file) || !file_exists($created_file)) {
      throw new \RuntimeException('File does not exist.');
    }

    $actual = File::contains($created_file, $string);

    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderContains(): array {
    return [
      ['FOO', 'empty.txt', FALSE],
      ['BAR', 'foobar_b.txt', TRUE],
      ['FOO', 'dir1/foobar_b.txt', TRUE],
      ['BAR', 'dir1/foobar_b.txt', TRUE],
      // Regex.
      ['/BA/', 'dir1/foobar_b.txt', TRUE],
      ['/BAW/', 'dir1/foobar_b.txt', FALSE],
      ['/BA.*/', 'dir1/foobar_b.txt', TRUE],
    ];
  }

  #[DataProvider('dataProviderContainsInDir')]
  public function testContainsInDir(string $string, array $files, array $excluded, array $expected): void {
    $dir = $this->locationsFixtureDir('tokens');

    $files = $this->flattenFileTree($files, $dir);
    static::locationsCopyFilesToSut($files, $dir, FALSE);

    $actual = File::containsInDir(static::$sut, $string, $excluded);

    $this->assertEquals(count($expected), count($actual));
    foreach ($actual as $path) {
      $path = str_replace(static::$sut . DIRECTORY_SEPARATOR, '', $path);
      $this->assertContains($path, $expected);
    }
  }

  public static function dataProviderContainsInDir(): array {
    return [
      ['FOO', ['empty.txt'], [], []],
      ['BAR', ['foobar_b.txt'], [], ['foobar_b.txt']],
      ['FOO', ['dir1/foobar_b.txt'], [], ['dir1/foobar_b.txt']],
      ['FOO', ['dir1/foobar_b.txt'], ['dir1'], []],
      ['BAR', ['dir1/foobar_b.txt'], [], ['dir1/foobar_b.txt']],

      // Regex.
      ['/BA/', ['dir1/foobar_b.txt'], [], ['dir1/foobar_b.txt']],
      ['/BA/', ['dir1/foobar_b.txt'], ['dir1'], []],
      ['/BAW/', ['dir1/foobar_b.txt'], [], []],
      ['/BA.*/', ['dir1/foobar_b.txt'], [], ['dir1/foobar_b.txt']],
    ];
  }

  #[DataProvider('dataProviderRemoveTokenInDir')]
  public function testRemoveTokenInDir(?string $token): void {
    $dir = $this->locationsFixtureDir('tokens_remove_dir') . DIRECTORY_SEPARATOR . 'before';
    (new Filesystem())->mirror($dir, static::$sut);
    static::$fixtures = $this->locationsFixtureDir('tokens_remove_dir') . DIRECTORY_SEPARATOR . 'after';

    File::removeTokenInDir(static::$sut, $token);

    $this->assertDirectoriesEqual(static::$fixtures, static::$sut);
  }

  public static function dataProviderRemoveTokenInDir(): array {
    return [
      'with_content_foo' => ['FOO'],
      'without_content_notoken' => [NULL],
    ];
  }

  #[DataProvider('dataProviderRemoveToken')]
  public function testRemoveToken(string $file, string $begin, string $end, bool $with_content, bool $expect_exception, string $expected_file): void {
    $dir = $this->locationsFixtureDir('tokens');

    $expected_files = $this->flattenFileTree([$expected_file], $dir);
    $expected_file = reset($expected_files);

    $files = $this->flattenFileTree([$file], $dir);

    $sut_files = static::locationsCopyFilesToSut($files, $dir);
    $sut_file = reset($sut_files);

    if (empty($sut_file) || !file_exists($sut_file)) {
      throw new \RuntimeException('File does not exist.');
    }

    if ($expect_exception) {
      $this->expectException(\RuntimeException::class);
    }

    File::removeToken($sut_file, $begin, $end, $with_content);

    $this->assertFileEquals($expected_file, $sut_file);
  }

  public static function dataProviderRemoveToken(): array {
    return [
      ['empty.txt', 'FOO', 'FOO', TRUE, FALSE, 'empty.txt'],

      // Different begin and end tokens.
      ['foobar_b.txt', '#;< FOO', '#;> BAR', TRUE, FALSE, 'lines_4.txt'],
      ['foobar_b.txt', '#;< FOO', '#;> BAR', FALSE, FALSE, 'lines_234.txt'],

      ['foobar_m.txt', '#;< FOO', '#;> BAR', TRUE, FALSE, 'lines_14.txt'],
      ['foobar_m.txt', '#;< FOO', '#;> BAR', FALSE, FALSE, 'lines_1234.txt'],

      ['foobar_e.txt', '#;< FOO', '#;> BAR', TRUE, FALSE, 'lines_1.txt'],
      ['foobar_e.txt', '#;< FOO', '#;> BAR', FALSE, FALSE, 'lines_123.txt'],

      // Same begin and end tokens.
      ['foofoo_b.txt', '#;< FOO', '#;> FOO', TRUE, FALSE, 'lines_4.txt'],
      ['foofoo_b.txt', '#;< FOO', '#;> FOO', FALSE, FALSE, 'lines_234.txt'],

      ['foofoo_m.txt', '#;< FOO', '#;> FOO', TRUE, FALSE, 'lines_14.txt'],
      ['foofoo_m.txt', '#;< FOO', '#;> FOO', FALSE, FALSE, 'lines_1234.txt'],

      ['foofoo_e.txt', '#;< FOO', '#;> FOO', TRUE, FALSE, 'lines_1.txt'],
      ['foofoo_e.txt', '#;< FOO', '#;> FOO', FALSE, FALSE, 'lines_123.txt'],

      // Tokens without ending trigger exception.
      ['foobar_b.txt', '#;< FOO', '#;> FOO', TRUE, TRUE, 'lines_4.txt'],
      ['foobar_b.txt', '#;< FOO', '#;> FOO', FALSE, TRUE, 'lines_234.txt'],

      ['foobar_m.txt', '#;< FOO', '#;> FOO', TRUE, TRUE, 'lines_14.txt'],
      ['foobar_m.txt', '#;< FOO', '#;> FOO', FALSE, TRUE, 'lines_1234.txt'],

      ['foobar_e.txt', '#;< FOO', '#;> FOO', TRUE, TRUE, 'lines_1.txt'],
      ['foobar_e.txt', '#;< FOO', '#;> FOO', FALSE, TRUE, 'lines_123.txt'],
    ];
  }

  #[DataProvider('dataProviderReplaceContentInDir')]
  public function testReplaceContentInDir(string $from, string $to, array $fixture_files, array $expected_files): void {
    $dir = $this->locationsFixtureDir('tokens');

    $expected_files = $this->flattenFileTree($expected_files, $dir);

    $fixture_files = $this->flattenFileTree($fixture_files, $dir);
    $sut_files = static::locationsCopyFilesToSut($fixture_files, $dir);
    if (count($sut_files) !== count($expected_files)) {
      throw new \RuntimeException('Provided files number is not equal to expected files number.');
    }

    File::replaceContentInDir(static::$sut, $from, $to);

    foreach (array_keys($sut_files) as $k) {
      $this->assertFileEquals($expected_files[$k], $sut_files[$k]);
    }
  }

  public static function dataProviderReplaceContentInDir(): array {
    return [
      [
        'BAR',
        'FOO',
        ['empty.txt'],
        ['empty.txt'],
      ],
      [
        'BAR',
        'FOO',
        ['foobar_b.txt', 'foobar_m.txt', 'foobar_e.txt'],
        ['foofoo_b.txt', 'foofoo_m.txt', 'foofoo_e.txt'],
      ],
      [
        'BAR',
        'FOO',
        ['dir1/foobar_b.txt'],
        ['dir1/foofoo_b.txt'],
      ],
      [
        '/BAR/',
        'FOO',
        ['dir1/foobar_b.txt'],
        ['dir1/foofoo_b.txt'],
      ],
    ];
  }

  #[DataProvider('dataProviderRemoveLine')]
  public function testRemoveLine(string $filename, string $content, string $needle, string $expected): void {
    $file = static::$workspace . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($file, $content);

    File::removeLine($file, $needle);
    $result = file_get_contents($file);

    $this->assertSame($expected, $result);

    unlink($file);
  }

  public static function dataProviderRemoveLine(): array {
    return [
      'remove single line' => [
        'test.txt',
        "line1\nremove me\nline3\n",
        'remove me',
        "line1\nline3\n",
      ],
      'remove multiple occurrences' => [
        'test.txt',
        "line1\nremove me\nline2\nremove me again\nline3\n",
        'remove me',
        "line1\nline2\nline3\n",
      ],
      'no match (no removal)' => [
        'test.txt',
        "line1\nline2\nline3\n",
        'not in file',
        "line1\nline2\nline3\n",
      ],
      'handle CRLF line endings' => [
        'test.txt',
        "line1\r\nremove me\r\nline3\r\n",
        'remove me',
        "line1\r\nline3\r\n",
      ],
      'handle old Mac line endings (CR)' => [
        'test.txt',
        "line1\rremove me\rline3\r",
        'remove me',
        "line1\rline3\r",
      ],
      'empty file' => [
        'test.txt',
        "",
        'anything',
        "",
      ],
      'excluded file' => [
        'test.png',
        "excluded\nremove me\n",
        'remove me',
        "excluded\nremove me\n",
      ],
    ];
  }

  #[DataProvider('dataProviderRenameInDir')]
  public function testRenameInDir(array $fixture_files, array $expected_files): void {
    $dir = $this->locationsFixtureDir('tokens');

    $expected_files = $this->flattenFileTree($expected_files, static::$sut);

    $fixture_files = $this->flattenFileTree($fixture_files, $dir);
    $sut_files = static::locationsCopyFilesToSut($fixture_files, $dir, FALSE);

    if (count($sut_files) !== count($expected_files)) {
      throw new \RuntimeException('Provided files number is not equal to expected files number.');
    }

    File::renameInDir(static::$sut, 'foo', 'bar');

    foreach (array_keys($expected_files) as $k) {
      $this->assertFileExists($expected_files[$k]);
    }
  }

  public static function dataProviderRenameInDir(): array {
    return [
      [
        ['empty.txt'],
        ['empty.txt'],
      ],
      [
        ['foofoo_b.txt'],
        ['barbar_b.txt'],
      ],
      [
        ['dir1/foofoo_b.txt'],
        ['dir1/barbar_b.txt'],
      ],
      [
        ['foo/foofoo_b.txt'],
        ['bar/barbar_b.txt'],
      ],
    ];
  }

  /**
   * Flatten file tree.
   *
   * @param array<string|int, string|array> $tree
   *   File tree.
   * @param string $parent
   *   Parent directory.
   *
   * @return array
   *   Flattened file tree.
   */
  protected function flattenFileTree(array $tree, string $parent = '.'): array {
    $flatten = [];

    foreach ($tree as $dir => $file) {
      if (is_array($file)) {
        $flatten = array_merge($flatten, $this->flattenFileTree($file, $parent . DIRECTORY_SEPARATOR . $dir));
      }
      else {
        $flatten[] = $parent . DIRECTORY_SEPARATOR . $file;
      }
    }

    return $flatten;
  }

}
