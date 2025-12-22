<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Internal\Replacer\Replacer;
use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\Replacer\Replacement;
use AlexSkrypnyk\File\Testing\DirectoryAssertionsTrait;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(File::class)]
final class FileStringsTest extends UnitTestCase {

  use DirectoryAssertionsTrait;

  public function testCopy(): void {
    self::$fixtures = $this->locationsFixtureDir();
    File::copy(self::$fixtures, self::$sut);

    $dir = self::$sut . DIRECTORY_SEPARATOR;

    $this->assertTrue(is_file($dir . 'file.txt'));
    $this->assertSame(0755, fileperms($dir . 'file.txt') & 0777);
    $this->assertDirectoryExists($dir . 'dir');
    $this->assertTrue(is_file($dir . 'dir/file_in_dir.txt'));
    $this->assertDirectoryExists($dir . 'dir/subdir');
    $this->assertTrue(is_file($dir . 'dir/subdir/file_in_subdir.txt'));

    $this->assertTrue(is_link($dir . 'file_link.txt'));

    $this->assertTrue(is_link($dir . 'dir_link'));
    $this->assertDirectoryExists($dir . 'dir_link/subdir');
    $this->assertTrue(is_file($dir . 'dir_link/subdir/file_in_subdir.txt'));
    $this->assertTrue(is_link($dir . 'dir_link/subdir/file_link_from_subdir.txt'));

    $this->assertTrue(is_link($dir . 'subdir_link_root'));
    $this->assertTrue(is_link($dir . 'subdir_link_root/file_link_from_subdir.txt'));
    $this->assertSame(0755, fileperms($dir . 'subdir_link_root/file_link_from_subdir.txt') & 0777);
    $this->assertTrue(is_file($dir . 'subdir_link_root/file_in_subdir.txt'));

    $this->assertTrue(is_link($dir . 'dir/subdir_link'));
    $this->assertDirectoryExists($dir . 'dir/subdir_link');

    $this->assertDirectoryDoesNotExist($dir . 'emptydir');
  }

  #[DataProvider('dataProviderContainsInFile')]
  public function testContainsInFile(string $string, string $file, mixed $expected): void {
    $dir = $this->locationsFixtureDir('tokens');

    $files = $this->flattenFileTree([$file], $dir);
    $created_files = self::locationsCopyFilesToSut($files, $dir);
    $created_file = reset($created_files);

    if (empty($created_file) || !file_exists($created_file)) {
      throw new FileException('File does not exist.');
    }

    $actual = File::contains($created_file, $string);

    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderContainsInFile(): \Iterator {
    yield ['FOO', 'empty.txt', FALSE];
    yield ['BAR', 'foobar_b.txt', TRUE];
    yield ['FOO', 'dir1/foobar_b.txt', TRUE];
    yield ['BAR', 'dir1/foobar_b.txt', TRUE];
    // Regex.
    yield ['/BA/', 'dir1/foobar_b.txt', TRUE];
    yield ['/BAW/', 'dir1/foobar_b.txt', FALSE];
    yield ['/BA.*/', 'dir1/foobar_b.txt', TRUE];
  }

  #[DataProvider('dataProviderContainsInDir')]
  public function testContainsInDir(string $string, array $files, array $excluded, array $expected): void {
    $dir = $this->locationsFixtureDir('tokens');

    $files = $this->flattenFileTree($files, $dir);
    self::locationsCopyFilesToSut($files, $dir, FALSE);

    $actual = File::findContainingInDir(self::$sut, $string, $excluded);

    $this->assertCount(count($expected), $actual);
    foreach ($actual as $path) {
      $path = str_replace(self::$sut . DIRECTORY_SEPARATOR, '', $path);
      $this->assertContains($path, $expected);
    }
  }

  public static function dataProviderContainsInDir(): \Iterator {
    yield ['FOO', ['empty.txt'], [], []];
    yield ['BAR', ['foobar_b.txt'], [], ['foobar_b.txt']];
    yield ['FOO', ['dir1/foobar_b.txt'], [], ['dir1/foobar_b.txt']];
    yield ['FOO', ['dir1/foobar_b.txt'], ['dir1'], []];
    yield ['BAR', ['dir1/foobar_b.txt'], [], ['dir1/foobar_b.txt']];
    // Regex.
    yield ['/BA/', ['dir1/foobar_b.txt'], [], ['dir1/foobar_b.txt']];
    yield ['/BA/', ['dir1/foobar_b.txt'], ['dir1'], []];
    yield ['/BAW/', ['dir1/foobar_b.txt'], [], []];
    yield ['/BA.*/', ['dir1/foobar_b.txt'], [], ['dir1/foobar_b.txt']];
  }

  #[DataProvider('dataProviderRemoveTokenInDir')]
  public function testRemoveTokenInDir(?string $token): void {
    $dir = $this->locationsFixtureDir('tokens_remove_dir') . DIRECTORY_SEPARATOR . 'before';
    (new Filesystem())->mirror($dir, self::$sut);
    self::$fixtures = $this->locationsFixtureDir('tokens_remove_dir') . DIRECTORY_SEPARATOR . 'after';

    File::removeTokenInDir(self::$sut, $token);

    // Compare directories by checking all files have identical content.
    $expected_files = File::scandir(self::$fixtures);
    $actual_files = File::scandir(self::$sut);
    $this->assertCount(count($expected_files), $actual_files, 'Directory should have the same number of files.');

    foreach ($expected_files as $expected_file) {
      $relative_path = str_replace((string) self::$fixtures, '', $expected_file);
      $actual_file = self::$sut . $relative_path;
      $this->assertFileExists($actual_file, sprintf('File %s should exist.', $relative_path));
      $this->assertFileEquals($expected_file, $actual_file, sprintf('File %s content should match.', $relative_path));
    }
  }

  public static function dataProviderRemoveTokenInDir(): \Iterator {
    yield 'with_content_foo' => ['FOO'];
    yield 'without_content_notoken' => [NULL];
  }

  #[DataProvider('dataProviderRemoveTokenInFileFixtures')]
  public function testRemoveTokenInFileFixtures(string $file, string $begin, string $end, bool $with_content, bool $expect_exception, string $expected_file): void {
    $dir = $this->locationsFixtureDir('tokens');

    $expected_files = $this->flattenFileTree([$expected_file], $dir);
    $expected_file = reset($expected_files);

    $files = $this->flattenFileTree([$file], $dir);

    $sut_files = self::locationsCopyFilesToSut($files, $dir);
    $sut_file = reset($sut_files);

    if (empty($sut_file) || !file_exists($sut_file)) {
      throw new FileException('File does not exist.');
    }

    if ($expect_exception) {
      $this->expectException(FileException::class);
    }

    File::removeTokenInFile($sut_file, $begin, $end, $with_content);

    $this->assertFileEquals($expected_file, $sut_file);
  }

  public static function dataProviderRemoveTokenInFileFixtures(): \Iterator {
    yield ['empty.txt', 'FOO', 'FOO', TRUE, FALSE, 'empty.txt'];
    // Different begin and end tokens.
    yield ['foobar_b.txt', '#;< FOO', '#;> BAR', TRUE, FALSE, 'lines_4.txt'];
    yield ['foobar_b.txt', '#;< FOO', '#;> BAR', FALSE, FALSE, 'lines_234.txt'];
    yield ['foobar_m.txt', '#;< FOO', '#;> BAR', TRUE, FALSE, 'lines_14.txt'];
    yield ['foobar_m.txt', '#;< FOO', '#;> BAR', FALSE, FALSE, 'lines_1234.txt'];
    yield ['foobar_e.txt', '#;< FOO', '#;> BAR', TRUE, FALSE, 'lines_1.txt'];
    yield ['foobar_e.txt', '#;< FOO', '#;> BAR', FALSE, FALSE, 'lines_123.txt'];
    // Same begin and end tokens.
    yield ['foofoo_b.txt', '#;< FOO', '#;> FOO', TRUE, FALSE, 'lines_4.txt'];
    yield ['foofoo_b.txt', '#;< FOO', '#;> FOO', FALSE, FALSE, 'lines_234.txt'];
    yield ['foofoo_m.txt', '#;< FOO', '#;> FOO', TRUE, FALSE, 'lines_14.txt'];
    yield ['foofoo_m.txt', '#;< FOO', '#;> FOO', FALSE, FALSE, 'lines_1234.txt'];
    yield ['foofoo_e.txt', '#;< FOO', '#;> FOO', TRUE, FALSE, 'lines_1.txt'];
    yield ['foofoo_e.txt', '#;< FOO', '#;> FOO', FALSE, FALSE, 'lines_123.txt'];
    // Tokens without ending trigger exception.
    yield ['foobar_b.txt', '#;< FOO', '#;> FOO', TRUE, TRUE, 'lines_4.txt'];
    yield ['foobar_b.txt', '#;< FOO', '#;> FOO', FALSE, TRUE, 'lines_234.txt'];
    yield ['foobar_m.txt', '#;< FOO', '#;> FOO', TRUE, TRUE, 'lines_14.txt'];
    yield ['foobar_m.txt', '#;< FOO', '#;> FOO', FALSE, TRUE, 'lines_1234.txt'];
    yield ['foobar_e.txt', '#;< FOO', '#;> FOO', TRUE, TRUE, 'lines_1.txt'];
    yield ['foobar_e.txt', '#;< FOO', '#;> FOO', FALSE, TRUE, 'lines_123.txt'];
  }

  #[DataProvider('dataProviderReplaceContentInDir')]
  public function testReplaceContentInDir(string|Replacement $from, string $to, array $fixture_files, array $expected_files): void {
    $dir = $this->locationsFixtureDir('tokens');

    $expected_files = $this->flattenFileTree($expected_files, $dir);

    $fixture_files = $this->flattenFileTree($fixture_files, $dir);
    $sut_files = self::locationsCopyFilesToSut($fixture_files, $dir);
    if (count($sut_files) !== count($expected_files)) {
      throw new FileException('Provided files number is not equal to expected files number.');
    }

    File::replaceContentInDir(self::$sut, $from, $to);

    sort($expected_files);
    sort($sut_files);

    foreach (array_keys($sut_files) as $k) {
      $this->assertFileEquals($expected_files[$k], $sut_files[$k]);
    }
  }

  public static function dataProviderReplaceContentInDir(): \Iterator {
    yield [
      'BAR',
      'FOO',
        ['empty.txt'],
        ['empty.txt'],
    ];
    yield [
      'BAR',
      'FOO',
        ['foobar_b.txt', 'foobar_m.txt', 'foobar_e.txt'],
        ['foofoo_b.txt', 'foofoo_m.txt', 'foofoo_e.txt'],
    ];
    yield [
      'BAR',
      'FOO',
        ['dir1/foobar_b.txt'],
        ['dir1/foofoo_b.txt'],
    ];
    yield [
      '/BAR/',
      'FOO',
        ['dir1/foobar_b.txt'],
        ['dir1/foofoo_b.txt'],
    ];
    // ReplacementInterface tests.
    yield [
      Replacement::create('test', 'BAR', 'FOO'),
      '',
        ['foobar_b.txt'],
        ['foofoo_b.txt'],
    ];
    yield [
      Replacement::create('test', '/BAR/', 'FOO'),
      '',
        ['dir1/foobar_b.txt'],
        ['dir1/foofoo_b.txt'],
    ];
  }

  #[DataProvider('dataProviderRemoveLine')]
  public function testRemoveLine(string $content, string $needle, string $expected): void {
    $result = File::removeLine($content, $needle);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderRemoveLine(): \Iterator {
    yield 'remove single line' => [
      "line1\nremove me\nline3\n",
      'remove me',
      "line1\nline3\n",
    ];
    yield 'remove multiple occurrences' => [
      "line1\nremove me\nline2\nremove me again\nline3\n",
      'remove me',
      "line1\nline2\nline3\n",
    ];
    yield 'no match' => [
      "line1\nline2\nline3\n",
      'not found',
      "line1\nline2\nline3\n",
    ];
    yield 'empty content' => [
      '',
      'needle',
      '',
    ];
    yield 'regex pattern' => [
      "FOO line1\nline2\nFOO line3\nline4\n",
      '/^FOO/',
      "line2\nline4\n",
    ];
    yield 'regex case insensitive' => [
      "FOO line1\nfoo line2\nline3\n",
      '/^foo/i',
      "line3\n",
    ];
    yield 'crlf line endings' => [
      "line1\r\nremove me\r\nline3\r\n",
      'remove me',
      "line1\r\nline3\r\n",
    ];
    yield 'cr line endings' => [
      "line1\rremove me\rline3\r",
      'remove me',
      "line1\rline3\r",
    ];
  }

  #[DataProvider('dataProviderRemoveLineInFile')]
  public function testRemoveLineInFile(string $filename, string $content, string $needle, string $expected): void {
    $file = self::$workspace . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($file, $content);

    File::removeLineInFile($file, $needle);
    $result = file_get_contents($file);

    $this->assertSame($expected, $result);

    unlink($file);
  }

  public static function dataProviderRemoveLineInFile(): \Iterator {
    yield 'remove single line' => [
      'test.txt',
      "line1\nremove me\nline3\n",
      'remove me',
      "line1\nline3\n",
    ];
    yield 'remove multiple occurrences' => [
      'test.txt',
      "line1\nremove me\nline2\nremove me again\nline3\n",
      'remove me',
      "line1\nline2\nline3\n",
    ];
    yield 'no match (no removal)' => [
      'test.txt',
      "line1\nline2\nline3\n",
      'not in file',
      "line1\nline2\nline3\n",
    ];
    yield 'handle CRLF line endings' => [
      'test.txt',
      "line1\r\nremove me\r\nline3\r\n",
      'remove me',
      "line1\r\nline3\r\n",
    ];
    yield 'handle old Mac line endings (CR)' => [
      'test.txt',
      "line1\rremove me\rline3\r",
      'remove me',
      "line1\rline3\r",
    ];
    yield 'empty file' => [
      'test.txt',
      "",
      'anything',
      "",
    ];
    yield 'excluded file' => [
      'test.png',
      "excluded\nremove me\n",
      'remove me',
      "excluded\nremove me\n",
    ];
    yield 'remove line containing ###' => [
      'test.txt',
      "line1\nremove me ### other\nline3\n",
      '###',
      "line1\nline3\n",
    ];
    yield 'remove line containing multiple  ###' => [
      'test.txt',
      "line1\nremove me ### other\nremove me ### other\nline3\n",
      '###',
      "line1\nline3\n",
    ];
    // Regex pattern tests.
    yield 'remove lines starting with FOO (regex)' => [
      'test.txt',
      "FOO line1\nline2\nFOOBAR line3\nline4\n",
      '/^FOO/',
      "line2\nline4\n",
    ];
    yield 'remove lines ending with BAR (regex)' => [
      'test.txt',
      "line1\nline2 BAR\nline3\nline4 BAR\n",
      '/BAR$/',
      "line1\nline3\n",
    ];
    yield 'remove lines matching pattern (regex)' => [
      'test.txt',
      "FOO line1 BAR\nline2\nFOO line3 BAR\nline4\n",
      '/FOO.*BAR/',
      "line2\nline4\n",
    ];
    yield 'remove lines case-insensitive (regex)' => [
      'test.txt',
      "FOO line1\nfoo line2\nFoO line3\nline4\n",
      '/foo/i',
      "line4\n",
    ];
    yield 'remove lines with digits (regex)' => [
      'test.txt',
      "line one\nline without digits\nline with 123\nline456\n",
      '/\d+/',
      "line one\nline without digits\n",
    ];
    yield 'remove lines with URL pattern (regex, # delimiter)' => [
      'test.txt',
      "line1\nhttp://example.com\nhttps://test.com\nline4\n",
      '#https?://#',
      "line1\nline4\n",
    ];
    yield 'remove lines with trailing whitespace (regex, ~ delimiter)' => [
      'test.txt',
      "line1\nline2  \nline3\nline4 \n",
      '~\s+$~',
      "line1\nline3\n",
    ];
    yield 'invalid regex treated as literal' => [
      'test.txt',
      "line1\ninvalid( line\nline3\n",
      '/invalid(/',
      "line1\ninvalid( line\nline3\n",
    ];
  }

  public function testRemoveLineInDir(): void {
    $subdir = self::$sut . DIRECTORY_SEPARATOR . 'subdir';
    mkdir($subdir);

    $file1 = self::$sut . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $subdir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, "line1\nremove me\nline3\n");
    file_put_contents($file2, "line1\nremove me\nline2\nremove me again\nline3\n");

    File::removeLineInDir(self::$sut, 'remove me');

    $this->assertSame("line1\nline3\n", file_get_contents($file1));
    $this->assertSame("line1\nline2\nline3\n", file_get_contents($file2));
  }

  public function testRemoveLineInDirWithRegex(): void {
    $file1 = self::$sut . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = self::$sut . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, "FOO line1\nline2\nFOOBAR line3\nline4\n");
    file_put_contents($file2, "line1\nFOO line2\nline3\n");

    File::removeLineInDir(self::$sut, '/^FOO/');

    $this->assertSame("line2\nline4\n", file_get_contents($file1));
    $this->assertSame("line1\nline3\n", file_get_contents($file2));
  }

  #[DataProvider('dataProviderRenameInDir')]
  public function testRenameInDir(array $fixture_files, array $expected_files): void {
    $dir = $this->locationsFixtureDir('tokens');

    $expected_files = $this->flattenFileTree($expected_files, self::$sut);

    $fixture_files = $this->flattenFileTree($fixture_files, $dir);
    $sut_files = self::locationsCopyFilesToSut($fixture_files, $dir, FALSE);

    if (count($sut_files) !== count($expected_files)) {
      throw new FileException('Provided files count is not equal to expected files count.');
    }

    File::renameInDir(self::$sut, 'foo', 'bar');

    foreach (array_keys($expected_files) as $k) {
      $this->assertFileExists($expected_files[$k]);
    }
  }

  public static function dataProviderRenameInDir(): \Iterator {
    yield [
        ['empty.txt'],
        ['empty.txt'],
    ];
    yield [
        ['foofoo_b.txt'],
        ['barbar_b.txt'],
    ];
    yield [
        ['dir1/foofoo_b.txt'],
        ['dir1/barbar_b.txt'],
    ];
    yield [
        ['foo/foofoo_b.txt'],
        ['bar/barbar_b.txt'],
    ];
  }

  public function testRenameInDirOverwrite(): void {
    $dir = $this->locationsFixtureDir('tokens');

    $source_files = [
      $dir . '/foo/foofoo_b.txt',
      $dir . '/foobar_b.txt',
    ];

    File::mkdir(self::$sut . '/bar');
    File::copy($source_files[0], self::$sut . '/foo/foofoo_b.txt');
    File::copy($source_files[1], self::$sut . '/bar/barbar_b.txt');

    $this->assertTrue(File::exists(self::$sut . '/foo/foofoo_b.txt'));
    $this->assertTrue(File::exists(self::$sut . '/bar/barbar_b.txt'));
    $this->assertTrue(File::contains(self::$sut . '/bar/barbar_b.txt', 'BAR'));

    File::renameInDir(self::$sut, 'foo', 'bar');

    $this->assertFileExists(self::$sut . '/bar/barbar_b.txt');
    $this->assertTrue(File::contains(self::$sut . '/bar/barbar_b.txt', 'FOO'));
    $this->assertFalse(File::contains(self::$sut . '/bar/barbar_b.txt', 'BAR'));
  }

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

  #[DataProvider('dataProviderReplaceContent')]
  public function testReplaceContent(string $content, string|Replacement $needle, string $replacement, string $expected): void {
    $result = File::replaceContent($content, $needle, $replacement);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderReplaceContent(): \Iterator {
    // Basic string operations.
    yield 'empty content' => ['', 'needle', 'replacement', ''];
    yield 'simple string replacement' => ['Hello, world!', 'world', 'everyone', 'Hello, everyone!'];
    yield 'multiple occurrences' => ['foo bar foo baz foo', 'foo', 'test', 'test bar test baz test'];
    yield 'no matches' => ['Hello, world!', 'xyz', 'replacement', 'Hello, world!'];
    yield 'replace with empty string' => ['Hello, world!', 'world', '', 'Hello, !'];
    yield 'multiline content' => ["line1\nHello, world!\nline3", 'world', 'universe', "line1\nHello, universe!\nline3"];
    yield 'special characters' => ['Price: $10.00 (tax: $1.50)', '$', '€', 'Price: €10.00 (tax: €1.50)'];
    yield 'unicode content' => ['Hello, 世界!', '世界', 'world', 'Hello, world!'];
    yield 'content with tabs and newlines' => ["line1\ttab\nline2\r\nline3", "\t", ' [TAB] ', "line1 [TAB] tab\nline2\r\nline3"];
    yield 'overlapping patterns' => ['aaabbbaaaccc', 'aaa', 'XXX', 'XXXbbbXXXccc'];
    // Regex operations.
    yield 'simple regex replacement' => ['Hello, world!', '/world/', 'universe', 'Hello, universe!'];
    yield 'regex with capture groups' => ['Hello, world!', '/Hello, (\w+)!/', 'Greetings, $1!', 'Greetings, world!'];
    yield 'complex regex pattern' => ['Email: user@example.com and another@test.org', '/(\w+)@(\w+\.\w+)/', '[$1 AT $2]', 'Email: [user AT example.com] and [another AT test.org]'];
    yield 'regex with case insensitive flag' => ['Hello, WORLD!', '/world/i', 'universe', 'Hello, universe!'];
    yield 'string that looks like regex but gets string replacement' => ['Hello, /world/!', '/world/', 'universe', 'Hello, /universe/!'];
    yield 'multiline regex' => ["start\nHello, world!\nend", '/Hello,.*!/m', 'Greetings!', "start\nGreetings!\nend"];
    yield 'regex matches whole string' => ['Hello, world!', '/.+/', 'replacement', 'replacement'];
    yield 'regex empty replacement' => ['Hello, world!', '/world/', '', 'Hello, !'];
    yield 'regex with no matches' => ['Hello, world!', '/xyz/', 'replacement', 'Hello, world!'];
    yield 'regex error handling' => ['test string', '/test/', 'new', 'new string'];
    // ReplacementInterface tests.
    yield 'replacement with string pattern' => ['Hello, world!', Replacement::create('test', 'world', 'everyone'), '', 'Hello, everyone!'];
    yield 'replacement with regex pattern' => ['Hello, world!', Replacement::create('test', '/world/', 'universe'), '', 'Hello, universe!'];
    yield 'replacement with capture groups' => ['Hello, world!', Replacement::create('test', '/Hello, (\w+)!/', 'Greetings, $1!'), '', 'Greetings, world!'];
    yield 'replacement with closure' => ['hello world', Replacement::create('test', strtoupper(...)), '', 'HELLO WORLD'];
    yield 'replacement with exclusion' => ['1.0.0 2.0.0 0.0.1', Replacement::create('version', '/\d+\.\d+\.\d+/', '__VERSION__')->addExclusion('/^0\./'), '', '__VERSION__ __VERSION__ 0.0.1'];
  }

  #[DataProvider('dataProviderCollapseRepeatedEmptyLines')]
  public function testCollapseRepeatedEmptyLines(string $input, string $expected): void {
    $actual = File::collapseEmptyLines($input);
    $this->assertSame($expected, $actual);
  }

  public static function dataProviderCollapseRepeatedEmptyLines(): \Iterator {
    yield 'empty lines' => [
      '',
      '',
    ];
    yield 'empty lines, newlines preserved' => [
      "\n\n",
      "",
    ];
    yield 'empty lines, newlines preserved and trimmed' => [
      "\n\n\n",
      "",
    ];
    yield 'single line' => [
      "line1",
      "line1",
    ];
    yield 'single line with trailing newlines' => [
      "line1\n\n",
      "line1\n",
    ];
    yield 'single line with 3 trailing newlines' => [
      "line1\n\n\n",
      "line1\n",
    ];
    yield 'single line with more trailing newlines' => [
      "line1\n\n\n\n",
      "line1\n",
    ];
    yield 'multiple consecutive empty lines' => [
      "line1\n\n\n\n\nline2",
      "line1\n\nline2",
    ];
    yield 'three consecutive empty lines' => [
      "line1\n\n\n\nline2",
      "line1\n\nline2",
    ];
    yield 'single empty line unchanged' => [
      "line1\n\nline2",
      "line1\n\nline2",
    ];
    yield 'no empty lines' => [
      "line1\nline2\nline3",
      "line1\nline2\nline3",
    ];
    yield 'empty lines with spaces' => [
      "line1\n  \n\t\n   \n\nline2",
      "line1\n\nline2",
    ];
    yield 'empty lines with mixed whitespace' => [
      "line1\n \t \n\n \n\t\t\nline2",
      "line1\n\nline2",
    ];
    yield 'tabs and spaces mixed' => [
      "line1\n\t\n  \n\t \n\nline2",
      "line1\n\nline2",
    ];
    yield 'empty lines at beginning' => [
      "\n\n\nline1\nline2",
      "line1\nline2",
    ];
    yield 'empty lines at beginning longer' => [
      "\n\n\n\n\n\nline1\nline2",
      "line1\nline2",
    ];
    yield 'empty lines at end' => [
      "line1\nline2\n\n\n\n",
      "line1\nline2\n",
    ];
    yield 'single newline at end preserved' => [
      "line1\nline2\n",
      "line1\nline2\n",
    ];
    // \r line endings
    yield 'empty lines, carriage returns preserved' => [
      "\r\r",
      "",
    ];
    yield 'empty lines, carriage returns preserved and trimmed' => [
      "\r\r\r",
      "",
    ];
    yield 'single line with trailing carriage returns' => [
      "line1\r\r",
      "line1\r",
    ];
    yield 'single line with more trailing carriage returns' => [
      "line1\r\r\r\r",
      "line1\r",
    ];
    yield 'multiple consecutive empty lines with carriage returns' => [
      "line1\r\r\r\r\rline2",
      "line1\r\rline2",
    ];
    yield 'three consecutive empty lines with carriage returns' => [
      "line1\r\r\r\rline2",
      "line1\r\rline2",
    ];
    yield 'single empty line unchanged with carriage returns' => [
      "line1\r\rline2",
      "line1\r\rline2",
    ];
    yield 'no empty lines with carriage returns' => [
      "line1\rline2\rline3",
      "line1\rline2\rline3",
    ];
    yield 'empty lines with spaces and carriage returns' => [
      "line1\r  \r\t\r   \r\rline2",
      "line1\r\rline2",
    ];
    yield 'empty lines with mixed whitespace and carriage returns' => [
      "line1\r \t \r\r \r\t\t\rline2",
      "line1\r\rline2",
    ];
    yield 'tabs and spaces mixed with carriage returns' => [
      "line1\r\t\r  \r\t \r\rline2",
      "line1\r\rline2",
    ];
    yield 'empty lines at beginning with carriage returns' => [
      "\r\r\rline1\rline2",
      "line1\rline2",
    ];
    yield 'empty lines at beginning longer with carriage returns' => [
      "\r\r\r\r\r\rline1\rline2",
      "line1\rline2",
    ];
    yield 'empty lines at end with carriage returns' => [
      "line1\rline2\r\r\r\r",
      "line1\rline2\r",
    ];
    yield 'single carriage return at end preserved' => [
      "line1\rline2\r",
      "line1\rline2\r",
    ];
    // \r\n line endings
    yield 'empty lines, crlf preserved' => [
      "\r\n\r\n",
      "",
    ];
    yield 'empty lines, crlf preserved and trimmed' => [
      "\r\n\r\n\r\n",
      "",
    ];
    yield 'single line with trailing crlf' => [
      "line1\r\n\r\n",
      "line1\r\n",
    ];
    yield 'single line with more trailing crlf' => [
      "line1\r\n\r\n\r\n\r\n",
      "line1\r\n",
    ];
    yield 'multiple consecutive empty lines with crlf' => [
      "line1\r\n\r\n\r\n\r\n\r\nline2",
      "line1\r\nline2",
    ];
    yield 'three consecutive empty lines with crlf' => [
      "line1\r\n\r\n\r\n\r\nline2",
      "line1\r\nline2",
    ];
    yield 'single empty line unchanged with crlf' => [
      "line1\r\n\r\nline2",
      "line1\r\nline2",
    ];
    yield 'no empty lines with crlf' => [
      "line1\r\nline2\r\nline3",
      "line1\r\nline2\r\nline3",
    ];
    yield 'empty lines with spaces and crlf' => [
      "line1\r\n  \r\n\t\r\n   \r\n\r\nline2",
      "line1\r\n\r\nline2",
    ];
    yield 'empty lines with mixed whitespace and crlf' => [
      "line1\r\n \t \r\n\r\n \r\n\t\t\r\nline2",
      "line1\r\n\r\nline2",
    ];
    yield 'tabs and spaces mixed with crlf' => [
      "line1\r\n\t\r\n  \r\n\t \r\n\r\nline2",
      "line1\r\n\r\nline2",
    ];
    yield 'empty lines at beginning with crlf' => [
      "\r\n\r\n\r\nline1\r\nline2",
      "line1\r\nline2",
    ];
    yield 'empty lines at beginning longer with crlf' => [
      "\r\n\r\n\r\n\r\n\r\n\r\nline1\r\nline2",
      "line1\r\nline2",
    ];
    yield 'empty lines at end with crlf' => [
      "line1\r\nline2\r\n\r\n\r\n\r\n",
      "line1\r\nline2\r\n",
    ];
    yield 'single crlf at end preserved' => [
      "line1\r\nline2\r\n",
      "line1\r\nline2\r\n",
    ];
  }

  public function testCollapseEmptyLinesInFile(): void {
    $file = self::$sut . DIRECTORY_SEPARATOR . 'collapse_test.txt';
    file_put_contents($file, "line1\n\n\n\n\nline2\n\n\n");

    File::collapseEmptyLinesInFile($file);

    $this->assertSame("line1\n\nline2\n", file_get_contents($file));
  }

  public function testCollapseEmptyLinesInFileNoChange(): void {
    $file = self::$sut . DIRECTORY_SEPARATOR . 'collapse_nochange.txt';
    file_put_contents($file, "line1\nline2\n");

    // Get original mtime.
    clearstatcache();
    $mtime_before = filemtime($file);

    sleep(1);

    File::collapseEmptyLinesInFile($file);

    // File should not be modified since content didn't change.
    clearstatcache();
    $mtime_after = filemtime($file);

    $this->assertSame("line1\nline2\n", file_get_contents($file));
    $this->assertSame($mtime_before, $mtime_after, 'File should not be modified when content unchanged');
  }

  public function testCollapseEmptyLinesInFileExcluded(): void {
    $file = self::$sut . DIRECTORY_SEPARATOR . 'image.png';
    $content = "line1\n\n\n\n\nline2\n";
    file_put_contents($file, $content);

    File::collapseEmptyLinesInFile($file);

    // Excluded file should not be modified.
    $this->assertSame($content, file_get_contents($file));
  }

  public function testCollapseEmptyLinesInDir(): void {
    $subdir = self::$sut . DIRECTORY_SEPARATOR . 'subdir';
    mkdir($subdir);

    $file1 = self::$sut . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $subdir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, "line1\n\n\n\n\nline2\n");
    file_put_contents($file2, "line1\n\n\nline2\n\n\n\nline3\n");

    File::collapseEmptyLinesInDir(self::$sut);

    $this->assertSame("line1\n\nline2\n", file_get_contents($file1));
    $this->assertSame("line1\n\nline2\n\nline3\n", file_get_contents($file2));
  }

  public function testCollapseEmptyLinesInDirWithExcludedFiles(): void {
    $file1 = self::$sut . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = self::$sut . DIRECTORY_SEPARATOR . 'image.jpg';

    $text_content = "line1\n\n\n\n\nline2\n";
    $image_content = "fake\n\n\n\n\nimage\n";

    file_put_contents($file1, $text_content);
    file_put_contents($file2, $image_content);

    File::collapseEmptyLinesInDir(self::$sut);

    $this->assertSame("line1\n\nline2\n", file_get_contents($file1));
    // Image file should not be modified.
    $this->assertSame($image_content, file_get_contents($file2));
  }

  #[DataProvider('dataProviderRemoveToken')]
  public function testRemoveToken(string $content, string $token_begin, ?string $token_end, bool $with_content, string $expected, bool $expect_exception = FALSE, string $exception_message = ''): void {
    if ($expect_exception) {
      $this->expectException(FileException::class);
      if ($exception_message !== '' && $exception_message !== '0') {
        $this->expectExceptionMessage($exception_message);
      }
    }

    $result = File::removeToken($content, $token_begin, $token_end, $with_content);
    if (!$expect_exception) {
      $this->assertSame($expected, $result);
    }
  }

  public static function dataProviderRemoveToken(): \Iterator {
    // Basic edge cases.
    yield 'empty content' => ['', 'TOKEN', 'TOKEN', FALSE, ''];
    yield 'zero content' => ['0', 'TOKEN', 'TOKEN', FALSE, '0'];
    yield 'content with no tokens' => ["line1\nline2\nline3", 'TOKEN', 'TOKEN', FALSE, "line1\nline2\nline3"];
    yield 'single line content with token' => ['TOKEN', 'TOKEN', 'TOKEN', FALSE, ''];
    yield 'multiple tokens on same line removes line' => ["line1\nTOKEN more content TOKEN\nline2", 'TOKEN', 'TOKEN', FALSE, "line1\nline2"];
    // Token removal without content.
    yield 'simple token removal' => ["line1\nTOKEN\nline3", 'TOKEN', 'TOKEN', FALSE, "line1\nline3"];
    yield 'token at end of content' => ["line1\nline2\nTOKEN", 'TOKEN', 'TOKEN', FALSE, "line1\nline2"];
    yield 'token at beginning' => ["TOKEN\nline2\nline3", 'TOKEN', 'TOKEN', FALSE, "line2\nline3"];
    // Token removal with content (different begin/end tokens)
    yield 'simple token with content removal' => ["START\ncontent inside\nEND\nafter", 'START', 'END', TRUE, 'after'];
    yield 'nested content within token' => ["before\nSTART\nline1\nline2\nline3\nEND\nafter", 'START', 'END', TRUE, "before\nafter"];
    yield 'multiple token pairs with content' => ["before\nSTART\ncontent1\nEND\nmiddle\nSTART\ncontent2\nEND\nafter", 'START', 'END', TRUE, "before\nmiddle\nafter"];
    // Line ending preservation.
    yield 'windows line endings (CRLF)' => ["line1\r\nTOKEN\r\nline3\r\n", 'TOKEN', 'TOKEN', FALSE, "line1\r\nline3\r\n"];
    yield 'old mac line endings (CR)' => ["line1\rTOKEN\rline3\r", 'TOKEN', 'TOKEN', FALSE, "line1\rline3\r"];
    yield 'unix line endings' => ["line1\nTOKEN\nline3\n", 'TOKEN', 'TOKEN', FALSE, "line1\nline3\n"];
    yield 'crlf with content removal' => ["START\r\ncontent\r\nEND\r\nafter\r\n", 'START', 'END', TRUE, "after\r\n"];
    // Special characters in tokens.
    yield 'tokens with square brackets' => ["line1\n[TOKEN]\nline3\n(TOKEN)\nline5", '[TOKEN]', '[TOKEN]', FALSE, "line1\nline3\n(TOKEN)\nline5"];
    yield 'tokens with parentheses' => ["line1\n[TOKEN]\nline3\n(TOKEN)\nline5", '(TOKEN)', '(TOKEN)', FALSE, "line1\n[TOKEN]\nline3\nline5"];
    yield 'tokens with regex special chars' => ["line1\n.*TOKEN.*\nline3", '.*TOKEN.*', '.*TOKEN.*', FALSE, "line1\nline3"];
    // Complex scenarios.
    yield 'overlapping token scenarios' => ["before\nSTART1\nSTART2\ncontent\nEND2\nEND1\nafter", 'START1', 'END1', TRUE, "before\nafter"];
    // Exception scenarios.
    yield 'mismatched token counts exception' => ["START\ncontent\nSTART\nmore content", 'START', 'END', FALSE, '', TRUE, 'Invalid begin and end token count'];
  }

  public function testRemoveTokenComplexScenarios(): void {
    // Test consecutive tokens (requires multiple operations).
    $content = "line1\nTOKEN1\nTOKEN2\nline4";
    $result = File::removeToken($content, 'TOKEN1', 'TOKEN1', FALSE);
    $result = File::removeToken($result, 'TOKEN2', 'TOKEN2', FALSE);
    $this->assertSame("line1\nline4", $result);
  }

  #[DataProvider('dataProviderRemoveTokenInFile')]
  public function testRemoveTokenInFile(string $filename, string $content, string $token_begin, string $token_end, bool $with_content, bool $expect_exception, array $assertions): void {
    $file_path = self::$workspace . DIRECTORY_SEPARATOR . $filename;

    // Create file only if content is provided.
    if ($content !== '') {
      file_put_contents($file_path, $content);
    }

    if ($expect_exception) {
      $this->expectException(FileException::class);
    }

    File::removeTokenInFile($file_path, $token_begin, $token_end, $with_content);

    if (!$expect_exception) {
      // Process assertions.
      foreach ($assertions as $assertion) {
        match ($assertion['type']) {
          'file_does_not_exist' => $this->assertFileDoesNotExist($file_path),
          'string_not_contains' => $this->assertStringNotContainsString($assertion['needle'], (string) file_get_contents($file_path)),
          'string_contains' => $this->assertStringContainsString($assertion['needle'], (string) file_get_contents($file_path)),
          default => throw new \InvalidArgumentException('Unknown assertion type: ' . $assertion['type']),
        };
      }
    }
  }

  public static function dataProviderRemoveTokenInFile(): \Iterator {
    $complex_content = <<<EOT
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

    $mismatched_content = <<<EOT
START
START
END
EOT;
    yield 'remove token markers only (keep content)' => [
      'test_token.txt',
      $complex_content,
      '#; TOKEN_START',
      '#; TOKEN_END',
      FALSE,
      FALSE,
      [
        ['type' => 'string_not_contains', 'needle' => '#; TOKEN_START'],
        ['type' => 'string_not_contains', 'needle' => '#; TOKEN_END'],
        ['type' => 'string_contains', 'needle' => 'This is content inside a token'],
      ],
    ];
    yield 'remove tokens and content' => [
      'test_token2.txt',
      $complex_content,
      '#; ANOTHER_TOKEN',
      '#; ANOTHER_TOKEN',
      TRUE,
      FALSE,
      [
        ['type' => 'string_not_contains', 'needle' => '#; ANOTHER_TOKEN'],
        ['type' => 'string_not_contains', 'needle' => 'More content inside another token'],
      ],
    ];
    yield 'mismatched token counts throws exception' => [
      'mismatched_tokens.txt',
      $mismatched_content,
      'START',
      'END',
      FALSE,
      TRUE,
      [],
    ];
    yield 'non-existent file does nothing' => [
      'does_not_exist.txt',
      '',
      'TOKEN',
      'TOKEN',
      FALSE,
      FALSE,
      [
        ['type' => 'file_does_not_exist'],
      ],
    ];
    yield 'excluded file (image) unchanged' => [
      'image.png',
      "TOKEN\ncontent\nTOKEN",
      'TOKEN',
      'TOKEN',
      FALSE,
      FALSE,
      [
        ['type' => 'string_contains', 'needle' => 'TOKEN'],
      ],
    ];
    yield 'empty file content' => [
      'empty.txt',
      '',
      'TOKEN',
      'TOKEN',
      FALSE,
      FALSE,
      [
        ['type' => 'string_not_contains', 'needle' => 'TOKEN'],
      ],
    ];
    yield 'single line with tokens' => [
      'single_line.txt',
      'START content END',
      'START',
      'END',
      TRUE,
      FALSE,
      [
        ['type' => 'string_not_contains', 'needle' => 'START'],
        ['type' => 'string_not_contains', 'needle' => 'END'],
        ['type' => 'string_not_contains', 'needle' => 'content'],
      ],
    ];
    yield 'excluded file extension .jpg' => [
      'photo.jpg',
      "TOKEN\nimage data\nTOKEN",
      'TOKEN',
      'TOKEN',
      FALSE,
      FALSE,
      [
        ['type' => 'string_contains', 'needle' => 'TOKEN'],
        ['type' => 'string_contains', 'needle' => 'image data'],
      ],
    ];
  }

  #[DataProvider('dataProviderReplaceContentInFile')]
  public function testReplaceContentInFile(string $filename, string $content, string|Replacement $needle, string $replacement, string $expected, bool $should_exist_before, bool $should_exist_after): void {
    $file_path = self::$workspace . DIRECTORY_SEPARATOR . $filename;

    if ($should_exist_before) {
      file_put_contents($file_path, $content);
    }

    File::replaceContentInFile($file_path, $needle, $replacement);

    if ($should_exist_after) {
      $this->assertFileExists($file_path);
      $actual_content = file_get_contents($file_path);
      $this->assertSame($expected, $actual_content);
      unlink($file_path);
    }
    else {
      $this->assertFileDoesNotExist($file_path);
    }
  }

  public static function dataProviderReplaceContentInFile(): \Iterator {
    // Basic functionality.
    yield 'basic string replacement' => ['basic.txt', 'Hello, world!', 'world', 'everyone', 'Hello, everyone!', TRUE, TRUE];
    yield 'regex replacement' => ['regex.txt', 'Hello, everyone!', '/Hello, (\w+)!/', 'Greetings, $1!', 'Greetings, everyone!', TRUE, TRUE];
    // Edge cases.
    yield 'empty file' => ['empty.txt', '', 'test', 'replacement', '', TRUE, TRUE];
    yield 'nonexistent file' => ['nonexistent.txt', '', 'test', 'replacement', '', FALSE, FALSE];
    yield 'excluded image file' => ['image.jpg', 'fake image content', 'fake', 'real', 'fake image content', TRUE, TRUE];
    yield 'excluded png file' => ['photo.png', 'png content here', 'png', 'jpeg', 'png content here', TRUE, TRUE];
    yield 'zero content file' => ['zero.txt', '0', 'test', 'replacement', '0', TRUE, TRUE];
    yield 'multiline content' => ['multi.txt', "line1\nHello, world!\nline3", 'world', 'universe', "line1\nHello, universe!\nline3", TRUE, TRUE];
    yield 'no matches in file' => ['nomatch.txt', 'Hello, world!', 'xyz', 'replacement', 'Hello, world!', TRUE, TRUE];
    yield 'complex regex pattern' => ['complex_regex.txt', 'Email: user@example.com', '/(\w+)@(\w+\.\w+)/', '[$1 AT $2]', 'Email: [user AT example.com]', TRUE, TRUE];
    yield 'unicode content in file' => ['unicode.txt', 'Hello, 世界!', '世界', 'world', 'Hello, world!', TRUE, TRUE];
    yield 'special characters' => ['special.txt', 'Price: $10.00 (tax: $1.50)', '$', '€', 'Price: €10.00 (tax: €1.50)', TRUE, TRUE];
    yield 'no content change' => ['nochange.txt', 'Hello, world!', 'xyz', 'replacement', 'Hello, world!', TRUE, TRUE];
    // ReplacementInterface tests.
    yield 'replacement with string pattern' => ['test.txt', 'Hello, world!', Replacement::create('test', 'world', 'everyone'), '', 'Hello, everyone!', TRUE, TRUE];
    yield 'replacement with regex pattern' => ['test.txt', 'Version: v1.2.3', Replacement::create('version', '/v\d+\.\d+\.\d+/', '__VERSION__'), '', 'Version: __VERSION__', TRUE, TRUE];
    yield 'replacement with closure' => ['test.txt', 'hello world', Replacement::create('test', strtoupper(...)), '', 'HELLO WORLD', TRUE, TRUE];
  }

  public function testReplacerInstanceFreshWithoutSetting(): void {
    // Ensure no Replacer is set.
    File::resetReplacer();

    // First replacement should not affect subsequent calls.
    $content1 = File::replaceContent('Hello, world!', 'world', 'universe');
    $this->assertSame('Hello, universe!', $content1);

    // Second replacement with different needle should work independently.
    // If Replacer was reused, the previous replacement would still be active.
    $content2 = File::replaceContent('Hello, world!', 'Hello', 'Greetings');
    $this->assertSame('Greetings, world!', $content2);

    // Verify 'world' was NOT replaced (proving fresh Replacer was used).
    $this->assertStringContainsString('world', $content2);
  }

  public function testReplacerInstanceSharedWhenSet(): void {
    // Ensure clean state.
    File::resetReplacer();

    // Create and set a custom Replacer with a replacement.
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('custom', 'foo', 'bar'));
    File::setReplacer($replacer);

    // The set Replacer should be used.
    $content1 = File::replaceContent('foo baz', 'baz', 'qux');
    // Both 'foo' (from set Replacer) and 'baz' (from call) should be replaced.
    $this->assertSame('bar qux', $content1);

    // Subsequent call should still have 'foo' replacement from set Replacer.
    $content2 = File::replaceContent('foo test', 'test', 'example');
    $this->assertSame('bar example', $content2);

    // Clean up.
    File::resetReplacer();
  }

  public function testReplacerReset(): void {
    // Set a custom Replacer.
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('custom', 'foo', 'bar'));
    File::setReplacer($replacer);

    // Verify the Replacer is active.
    $content1 = File::replaceContent('foo test', 'test', 'example');
    $this->assertSame('bar example', $content1);

    // Reset the Replacer.
    File::resetReplacer();

    // Now 'foo' should NOT be replaced (fresh Replacer without the rule).
    $content2 = File::replaceContent('foo test', 'test', 'example');
    $this->assertSame('foo example', $content2);
  }

}
