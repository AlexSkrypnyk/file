<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Tests\Traits\DirectoryAssertionsTrait;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(File::class)]
class FileStringsTest extends UnitTestCase {

  use DirectoryAssertionsTrait;

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

  #[DataProvider('dataProviderContainsInFile')]
  public function testContainsInFile(string $string, string $file, mixed $expected): void {
    $dir = $this->locationsFixtureDir('tokens');

    $files = $this->flattenFileTree([$file], $dir);
    $created_files = static::locationsCopyFilesToSut($files, $dir);
    $created_file = reset($created_files);

    if (empty($created_file) || !file_exists($created_file)) {
      throw new FileException('File does not exist.');
    }

    $actual = File::contains($created_file, $string);

    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderContainsInFile(): array {
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

    $actual = File::findContainingInDir(static::$sut, $string, $excluded);

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

    // Compare directories by checking all files have identical content.
    $expected_files = File::scandir(static::$fixtures);
    $actual_files = File::scandir(static::$sut);
    $this->assertCount(count($expected_files), $actual_files, 'Directory should have the same number of files.');

    foreach ($expected_files as $expected_file) {
      $relative_path = str_replace((string) static::$fixtures, '', $expected_file);
      $actual_file = static::$sut . $relative_path;
      $this->assertFileExists($actual_file, sprintf('File %s should exist.', $relative_path));
      $this->assertFileEquals($expected_file, $actual_file, sprintf('File %s content should match.', $relative_path));
    }
  }

  public static function dataProviderRemoveTokenInDir(): array {
    return [
      'with_content_foo' => ['FOO'],
      'without_content_notoken' => [NULL],
    ];
  }

  #[DataProvider('dataProviderRemoveTokenInFileFixtures')]
  public function testRemoveTokenInFileFixtures(string $file, string $begin, string $end, bool $with_content, bool $expect_exception, string $expected_file): void {
    $dir = $this->locationsFixtureDir('tokens');

    $expected_files = $this->flattenFileTree([$expected_file], $dir);
    $expected_file = reset($expected_files);

    $files = $this->flattenFileTree([$file], $dir);

    $sut_files = static::locationsCopyFilesToSut($files, $dir);
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

  public static function dataProviderRemoveTokenInFileFixtures(): array {
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
      throw new FileException('Provided files number is not equal to expected files number.');
    }

    File::replaceContentInDir(static::$sut, $from, $to);

    sort($expected_files);
    sort($sut_files);

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
  public function testRemoveLine(string $content, string $needle, string $expected): void {
    $result = File::removeLine($content, $needle);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderRemoveLine(): array {
    return [
      'remove single line' => [
        "line1\nremove me\nline3\n",
        'remove me',
        "line1\nline3\n",
      ],
      'remove multiple occurrences' => [
        "line1\nremove me\nline2\nremove me again\nline3\n",
        'remove me',
        "line1\nline2\nline3\n",
      ],
      'no match' => [
        "line1\nline2\nline3\n",
        'not found',
        "line1\nline2\nline3\n",
      ],
      'empty content' => [
        '',
        'needle',
        '',
      ],
      'regex pattern' => [
        "FOO line1\nline2\nFOO line3\nline4\n",
        '/^FOO/',
        "line2\nline4\n",
      ],
      'regex case insensitive' => [
        "FOO line1\nfoo line2\nline3\n",
        '/^foo/i',
        "line3\n",
      ],
      'crlf line endings' => [
        "line1\r\nremove me\r\nline3\r\n",
        'remove me',
        "line1\r\nline3\r\n",
      ],
      'cr line endings' => [
        "line1\rremove me\rline3\r",
        'remove me',
        "line1\rline3\r",
      ],
    ];
  }

  #[DataProvider('dataProviderRemoveLineInFile')]
  public function testRemoveLineInFile(string $filename, string $content, string $needle, string $expected): void {
    $file = static::$workspace . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($file, $content);

    File::removeLineInFile($file, $needle);
    $result = file_get_contents($file);

    $this->assertSame($expected, $result);

    unlink($file);
  }

  public static function dataProviderRemoveLineInFile(): array {
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
      'remove line containing ###' => [
        'test.txt',
        "line1\nremove me ### other\nline3\n",
        '###',
        "line1\nline3\n",
      ],
      'remove line containing multiple  ###' => [
        'test.txt',
        "line1\nremove me ### other\nremove me ### other\nline3\n",
        '###',
        "line1\nline3\n",
      ],
      // Regex pattern tests.
      'remove lines starting with FOO (regex)' => [
        'test.txt',
        "FOO line1\nline2\nFOOBAR line3\nline4\n",
        '/^FOO/',
        "line2\nline4\n",
      ],
      'remove lines ending with BAR (regex)' => [
        'test.txt',
        "line1\nline2 BAR\nline3\nline4 BAR\n",
        '/BAR$/',
        "line1\nline3\n",
      ],
      'remove lines matching pattern (regex)' => [
        'test.txt',
        "FOO line1 BAR\nline2\nFOO line3 BAR\nline4\n",
        '/FOO.*BAR/',
        "line2\nline4\n",
      ],
      'remove lines case-insensitive (regex)' => [
        'test.txt',
        "FOO line1\nfoo line2\nFoO line3\nline4\n",
        '/foo/i',
        "line4\n",
      ],
      'remove lines with digits (regex)' => [
        'test.txt',
        "line one\nline without digits\nline with 123\nline456\n",
        '/\d+/',
        "line one\nline without digits\n",
      ],
      'remove lines with URL pattern (regex, # delimiter)' => [
        'test.txt',
        "line1\nhttp://example.com\nhttps://test.com\nline4\n",
        '#https?://#',
        "line1\nline4\n",
      ],
      'remove lines with trailing whitespace (regex, ~ delimiter)' => [
        'test.txt',
        "line1\nline2  \nline3\nline4 \n",
        '~\s+$~',
        "line1\nline3\n",
      ],
      'invalid regex treated as literal' => [
        'test.txt',
        "line1\ninvalid( line\nline3\n",
        '/invalid(/',
        "line1\ninvalid( line\nline3\n",
      ],
    ];
  }

  public function testRemoveLineInDir(): void {
    $subdir = static::$sut . DIRECTORY_SEPARATOR . 'subdir';
    mkdir($subdir);

    $file1 = static::$sut . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $subdir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, "line1\nremove me\nline3\n");
    file_put_contents($file2, "line1\nremove me\nline2\nremove me again\nline3\n");

    File::removeLineInDir(static::$sut, 'remove me');

    $this->assertSame("line1\nline3\n", file_get_contents($file1));
    $this->assertSame("line1\nline2\nline3\n", file_get_contents($file2));
  }

  public function testRemoveLineInDirWithRegex(): void {
    $file1 = static::$sut . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = static::$sut . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, "FOO line1\nline2\nFOOBAR line3\nline4\n");
    file_put_contents($file2, "line1\nFOO line2\nline3\n");

    File::removeLineInDir(static::$sut, '/^FOO/');

    $this->assertSame("line2\nline4\n", file_get_contents($file1));
    $this->assertSame("line1\nline3\n", file_get_contents($file2));
  }

  #[DataProvider('dataProviderRenameInDir')]
  public function testRenameInDir(array $fixture_files, array $expected_files): void {
    $dir = $this->locationsFixtureDir('tokens');

    $expected_files = $this->flattenFileTree($expected_files, static::$sut);

    $fixture_files = $this->flattenFileTree($fixture_files, $dir);
    $sut_files = static::locationsCopyFilesToSut($fixture_files, $dir, FALSE);

    if (count($sut_files) !== count($expected_files)) {
      throw new FileException('Provided files count is not equal to expected files count.');
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

  public function testRenameInDirOverwrite(): void {
    $dir = $this->locationsFixtureDir('tokens');

    $source_files = [
      $dir . '/foo/foofoo_b.txt',
      $dir . '/foobar_b.txt',
    ];

    File::mkdir(static::$sut . '/bar');
    File::copy($source_files[0], static::$sut . '/foo/foofoo_b.txt');
    File::copy($source_files[1], static::$sut . '/bar/barbar_b.txt');

    $this->assertTrue(File::exists(static::$sut . '/foo/foofoo_b.txt'));
    $this->assertTrue(File::exists(static::$sut . '/bar/barbar_b.txt'));
    $this->assertTrue(File::contains(static::$sut . '/bar/barbar_b.txt', 'BAR'));

    File::renameInDir(static::$sut, 'foo', 'bar');

    $this->assertFileExists(static::$sut . '/bar/barbar_b.txt');
    $this->assertTrue(File::contains(static::$sut . '/bar/barbar_b.txt', 'FOO'));
    $this->assertFalse(File::contains(static::$sut . '/bar/barbar_b.txt', 'BAR'));
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
  public function testReplaceContent(string $content, string $needle, string $replacement, string $expected): void {
    $result = File::replaceContent($content, $needle, $replacement);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderReplaceContent(): array {
    return [
      // Basic string operations.
      'empty content' => ['', 'needle', 'replacement', ''],
      'simple string replacement' => ['Hello, world!', 'world', 'everyone', 'Hello, everyone!'],
      'multiple occurrences' => ['foo bar foo baz foo', 'foo', 'test', 'test bar test baz test'],
      'no matches' => ['Hello, world!', 'xyz', 'replacement', 'Hello, world!'],
      'replace with empty string' => ['Hello, world!', 'world', '', 'Hello, !'],
      'multiline content' => ["line1\nHello, world!\nline3", 'world', 'universe', "line1\nHello, universe!\nline3"],
      'special characters' => ['Price: $10.00 (tax: $1.50)', '$', '€', 'Price: €10.00 (tax: €1.50)'],
      'unicode content' => ['Hello, 世界!', '世界', 'world', 'Hello, world!'],
      'content with tabs and newlines' => ["line1\ttab\nline2\r\nline3", "\t", ' [TAB] ', "line1 [TAB] tab\nline2\r\nline3"],
      'overlapping patterns' => ['aaabbbaaaccc', 'aaa', 'XXX', 'XXXbbbXXXccc'],

      // Regex operations.
      'simple regex replacement' => ['Hello, world!', '/world/', 'universe', 'Hello, universe!'],
      'regex with capture groups' => ['Hello, world!', '/Hello, (\w+)!/', 'Greetings, $1!', 'Greetings, world!'],
      'complex regex pattern' => ['Email: user@example.com and another@test.org', '/(\w+)@(\w+\.\w+)/', '[$1 AT $2]', 'Email: [user AT example.com] and [another AT test.org]'],
      'regex with case insensitive flag' => ['Hello, WORLD!', '/world/i', 'universe', 'Hello, universe!'],
      'string that looks like regex but gets string replacement' => ['Hello, /world/!', '/world/', 'universe', 'Hello, /universe/!'],
      'multiline regex' => ["start\nHello, world!\nend", '/Hello,.*!/m', 'Greetings!', "start\nGreetings!\nend"],
      'regex matches whole string' => ['Hello, world!', '/.+/', 'replacement', 'replacement'],
      'regex empty replacement' => ['Hello, world!', '/world/', '', 'Hello, !'],
      'regex with no matches' => ['Hello, world!', '/xyz/', 'replacement', 'Hello, world!'],
      'regex error handling' => ['test string', '/test/', 'new', 'new string'],
    ];
  }

  #[DataProvider('dataProviderCollapseRepeatedEmptyLines')]
  public function testCollapseRepeatedEmptyLines(string $input, string $expected): void {
    $actual = File::collapseEmptyLines($input);
    $this->assertSame($expected, $actual);
  }

  public static function dataProviderCollapseRepeatedEmptyLines(): array {
    return [
      'empty lines' => [
        '',
        '',
      ],
      'empty lines, newlines preserved' => [
        "\n\n",
        "",
      ],
      'empty lines, newlines preserved and trimmed' => [
        "\n\n\n",
        "",
      ],
      'single line' => [
        "line1",
        "line1",
      ],
      'single line with trailing newlines' => [
        "line1\n\n",
        "line1\n",
      ],
      'single line with 3 trailing newlines' => [
        "line1\n\n\n",
        "line1\n",
      ],
      'single line with more trailing newlines' => [
        "line1\n\n\n\n",
        "line1\n",
      ],
      'multiple consecutive empty lines' => [
        "line1\n\n\n\n\nline2",
        "line1\n\nline2",
      ],
      'three consecutive empty lines' => [
        "line1\n\n\n\nline2",
        "line1\n\nline2",
      ],
      'single empty line unchanged' => [
        "line1\n\nline2",
        "line1\n\nline2",
      ],
      'no empty lines' => [
        "line1\nline2\nline3",
        "line1\nline2\nline3",
      ],
      'empty lines with spaces' => [
        "line1\n  \n\t\n   \n\nline2",
        "line1\n\nline2",
      ],
      'empty lines with mixed whitespace' => [
        "line1\n \t \n\n \n\t\t\nline2",
        "line1\n\nline2",
      ],
      'tabs and spaces mixed' => [
        "line1\n\t\n  \n\t \n\nline2",
        "line1\n\nline2",
      ],
      'empty lines at beginning' => [
        "\n\n\nline1\nline2",
        "line1\nline2",
      ],
      'empty lines at beginning longer' => [
        "\n\n\n\n\n\nline1\nline2",
        "line1\nline2",
      ],
      'empty lines at end' => [
        "line1\nline2\n\n\n\n",
        "line1\nline2\n",
      ],
      'single newline at end preserved' => [
        "line1\nline2\n",
        "line1\nline2\n",
      ],
      // \r line endings
      'empty lines, carriage returns preserved' => [
        "\r\r",
        "",
      ],
      'empty lines, carriage returns preserved and trimmed' => [
        "\r\r\r",
        "",
      ],
      'single line with trailing carriage returns' => [
        "line1\r\r",
        "line1\r",
      ],
      'single line with more trailing carriage returns' => [
        "line1\r\r\r\r",
        "line1\r",
      ],
      'multiple consecutive empty lines with carriage returns' => [
        "line1\r\r\r\r\rline2",
        "line1\r\rline2",
      ],
      'three consecutive empty lines with carriage returns' => [
        "line1\r\r\r\rline2",
        "line1\r\rline2",
      ],
      'single empty line unchanged with carriage returns' => [
        "line1\r\rline2",
        "line1\r\rline2",
      ],
      'no empty lines with carriage returns' => [
        "line1\rline2\rline3",
        "line1\rline2\rline3",
      ],
      'empty lines with spaces and carriage returns' => [
        "line1\r  \r\t\r   \r\rline2",
        "line1\r\rline2",
      ],
      'empty lines with mixed whitespace and carriage returns' => [
        "line1\r \t \r\r \r\t\t\rline2",
        "line1\r\rline2",
      ],
      'tabs and spaces mixed with carriage returns' => [
        "line1\r\t\r  \r\t \r\rline2",
        "line1\r\rline2",
      ],
      'empty lines at beginning with carriage returns' => [
        "\r\r\rline1\rline2",
        "line1\rline2",
      ],
      'empty lines at beginning longer with carriage returns' => [
        "\r\r\r\r\r\rline1\rline2",
        "line1\rline2",
      ],
      'empty lines at end with carriage returns' => [
        "line1\rline2\r\r\r\r",
        "line1\rline2\r",
      ],
      'single carriage return at end preserved' => [
        "line1\rline2\r",
        "line1\rline2\r",
      ],
      // \r\n line endings
      'empty lines, crlf preserved' => [
        "\r\n\r\n",
        "",
      ],
      'empty lines, crlf preserved and trimmed' => [
        "\r\n\r\n\r\n",
        "",
      ],
      'single line with trailing crlf' => [
        "line1\r\n\r\n",
        "line1\r\n",
      ],
      'single line with more trailing crlf' => [
        "line1\r\n\r\n\r\n\r\n",
        "line1\r\n",
      ],
      'multiple consecutive empty lines with crlf' => [
        "line1\r\n\r\n\r\n\r\n\r\nline2",
        "line1\r\nline2",
      ],
      'three consecutive empty lines with crlf' => [
        "line1\r\n\r\n\r\n\r\nline2",
        "line1\r\nline2",
      ],
      'single empty line unchanged with crlf' => [
        "line1\r\n\r\nline2",
        "line1\r\nline2",
      ],
      'no empty lines with crlf' => [
        "line1\r\nline2\r\nline3",
        "line1\r\nline2\r\nline3",
      ],
      'empty lines with spaces and crlf' => [
        "line1\r\n  \r\n\t\r\n   \r\n\r\nline2",
        "line1\r\n\r\nline2",
      ],
      'empty lines with mixed whitespace and crlf' => [
        "line1\r\n \t \r\n\r\n \r\n\t\t\r\nline2",
        "line1\r\n\r\nline2",
      ],
      'tabs and spaces mixed with crlf' => [
        "line1\r\n\t\r\n  \r\n\t \r\n\r\nline2",
        "line1\r\n\r\nline2",
      ],
      'empty lines at beginning with crlf' => [
        "\r\n\r\n\r\nline1\r\nline2",
        "line1\r\nline2",
      ],
      'empty lines at beginning longer with crlf' => [
        "\r\n\r\n\r\n\r\n\r\n\r\nline1\r\nline2",
        "line1\r\nline2",
      ],
      'empty lines at end with crlf' => [
        "line1\r\nline2\r\n\r\n\r\n\r\n",
        "line1\r\nline2\r\n",
      ],
      'single crlf at end preserved' => [
        "line1\r\nline2\r\n",
        "line1\r\nline2\r\n",
      ],
    ];
  }

  public function testCollapseEmptyLinesInFile(): void {
    $file = static::$sut . DIRECTORY_SEPARATOR . 'collapse_test.txt';
    file_put_contents($file, "line1\n\n\n\n\nline2\n\n\n");

    File::collapseEmptyLinesInFile($file);

    $this->assertSame("line1\n\nline2\n", file_get_contents($file));
  }

  public function testCollapseEmptyLinesInFileNoChange(): void {
    $file = static::$sut . DIRECTORY_SEPARATOR . 'collapse_nochange.txt';
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
    $file = static::$sut . DIRECTORY_SEPARATOR . 'image.png';
    $content = "line1\n\n\n\n\nline2\n";
    file_put_contents($file, $content);

    File::collapseEmptyLinesInFile($file);

    // Excluded file should not be modified.
    $this->assertSame($content, file_get_contents($file));
  }

  public function testCollapseEmptyLinesInDir(): void {
    $subdir = static::$sut . DIRECTORY_SEPARATOR . 'subdir';
    mkdir($subdir);

    $file1 = static::$sut . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $subdir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, "line1\n\n\n\n\nline2\n");
    file_put_contents($file2, "line1\n\n\nline2\n\n\n\nline3\n");

    File::collapseEmptyLinesInDir(static::$sut);

    $this->assertSame("line1\n\nline2\n", file_get_contents($file1));
    $this->assertSame("line1\n\nline2\n\nline3\n", file_get_contents($file2));
  }

  public function testCollapseEmptyLinesInDirWithExcludedFiles(): void {
    $file1 = static::$sut . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = static::$sut . DIRECTORY_SEPARATOR . 'image.jpg';

    $text_content = "line1\n\n\n\n\nline2\n";
    $image_content = "fake\n\n\n\n\nimage\n";

    file_put_contents($file1, $text_content);
    file_put_contents($file2, $image_content);

    File::collapseEmptyLinesInDir(static::$sut);

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

  public static function dataProviderRemoveToken(): array {
    return [
      // Basic edge cases.
      'empty content' => ['', 'TOKEN', 'TOKEN', FALSE, ''],
      'zero content' => ['0', 'TOKEN', 'TOKEN', FALSE, '0'],
      'content with no tokens' => ["line1\nline2\nline3", 'TOKEN', 'TOKEN', FALSE, "line1\nline2\nline3"],
      'single line content with token' => ['TOKEN', 'TOKEN', 'TOKEN', FALSE, ''],
      'multiple tokens on same line removes line' => ["line1\nTOKEN more content TOKEN\nline2", 'TOKEN', 'TOKEN', FALSE, "line1\nline2"],

      // Token removal without content.
      'simple token removal' => ["line1\nTOKEN\nline3", 'TOKEN', 'TOKEN', FALSE, "line1\nline3"],
      'token at end of content' => ["line1\nline2\nTOKEN", 'TOKEN', 'TOKEN', FALSE, "line1\nline2"],
      'token at beginning' => ["TOKEN\nline2\nline3", 'TOKEN', 'TOKEN', FALSE, "line2\nline3"],

      // Token removal with content (different begin/end tokens)
      'simple token with content removal' => ["START\ncontent inside\nEND\nafter", 'START', 'END', TRUE, 'after'],
      'nested content within token' => ["before\nSTART\nline1\nline2\nline3\nEND\nafter", 'START', 'END', TRUE, "before\nafter"],
      'multiple token pairs with content' => ["before\nSTART\ncontent1\nEND\nmiddle\nSTART\ncontent2\nEND\nafter", 'START', 'END', TRUE, "before\nmiddle\nafter"],

      // Line ending preservation.
      'windows line endings (CRLF)' => ["line1\r\nTOKEN\r\nline3\r\n", 'TOKEN', 'TOKEN', FALSE, "line1\r\nline3\r\n"],
      'old mac line endings (CR)' => ["line1\rTOKEN\rline3\r", 'TOKEN', 'TOKEN', FALSE, "line1\rline3\r"],
      'unix line endings' => ["line1\nTOKEN\nline3\n", 'TOKEN', 'TOKEN', FALSE, "line1\nline3\n"],
      'crlf with content removal' => ["START\r\ncontent\r\nEND\r\nafter\r\n", 'START', 'END', TRUE, "after\r\n"],

      // Special characters in tokens.
      'tokens with square brackets' => ["line1\n[TOKEN]\nline3\n(TOKEN)\nline5", '[TOKEN]', '[TOKEN]', FALSE, "line1\nline3\n(TOKEN)\nline5"],
      'tokens with parentheses' => ["line1\n[TOKEN]\nline3\n(TOKEN)\nline5", '(TOKEN)', '(TOKEN)', FALSE, "line1\n[TOKEN]\nline3\nline5"],
      'tokens with regex special chars' => ["line1\n.*TOKEN.*\nline3", '.*TOKEN.*', '.*TOKEN.*', FALSE, "line1\nline3"],

      // Complex scenarios.
      'overlapping token scenarios' => ["before\nSTART1\nSTART2\ncontent\nEND2\nEND1\nafter", 'START1', 'END1', TRUE, "before\nafter"],

      // Exception scenarios.
      'mismatched token counts exception' => ["START\ncontent\nSTART\nmore content", 'START', 'END', FALSE, '', TRUE, 'Invalid begin and end token count'],
    ];
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
    $file_path = static::$workspace . DIRECTORY_SEPARATOR . $filename;

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

  public static function dataProviderRemoveTokenInFile(): array {
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

    return [
      'remove token markers only (keep content)' => [
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
      ],
      'remove tokens and content' => [
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
      ],
      'mismatched token counts throws exception' => [
        'mismatched_tokens.txt',
        $mismatched_content,
        'START',
        'END',
        FALSE,
        TRUE,
        [],
      ],
      'non-existent file does nothing' => [
        'does_not_exist.txt',
        '',
        'TOKEN',
        'TOKEN',
        FALSE,
        FALSE,
        [
          ['type' => 'file_does_not_exist'],
        ],
      ],
      'excluded file (image) unchanged' => [
        'image.png',
        "TOKEN\ncontent\nTOKEN",
        'TOKEN',
        'TOKEN',
        FALSE,
        FALSE,
        [
          ['type' => 'string_contains', 'needle' => 'TOKEN'],
        ],
      ],
      'empty file content' => [
        'empty.txt',
        '',
        'TOKEN',
        'TOKEN',
        FALSE,
        FALSE,
        [
          ['type' => 'string_not_contains', 'needle' => 'TOKEN'],
        ],
      ],
      'single line with tokens' => [
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
      ],
      'excluded file extension .jpg' => [
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
      ],
    ];
  }

  #[DataProvider('dataProviderReplaceContentInFile')]
  public function testReplaceContentInFile(string $filename, string $content, string $needle, string $replacement, string $expected, bool $should_exist_before, bool $should_exist_after): void {
    $file_path = static::$workspace . DIRECTORY_SEPARATOR . $filename;

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

  public static function dataProviderReplaceContentInFile(): array {
    return [
      // Basic functionality.
      'basic string replacement' => ['basic.txt', 'Hello, world!', 'world', 'everyone', 'Hello, everyone!', TRUE, TRUE],
      'regex replacement' => ['regex.txt', 'Hello, everyone!', '/Hello, (\w+)!/', 'Greetings, $1!', 'Greetings, everyone!', TRUE, TRUE],

      // Edge cases.
      'empty file' => ['empty.txt', '', 'test', 'replacement', '', TRUE, TRUE],
      'nonexistent file' => ['nonexistent.txt', '', 'test', 'replacement', '', FALSE, FALSE],
      'excluded image file' => ['image.jpg', 'fake image content', 'fake', 'real', 'fake image content', TRUE, TRUE],
      'excluded png file' => ['photo.png', 'png content here', 'png', 'jpeg', 'png content here', TRUE, TRUE],
      'zero content file' => ['zero.txt', '0', 'test', 'replacement', '0', TRUE, TRUE],
      'multiline content' => ['multi.txt', "line1\nHello, world!\nline3", 'world', 'universe', "line1\nHello, universe!\nline3", TRUE, TRUE],
      'no matches in file' => ['nomatch.txt', 'Hello, world!', 'xyz', 'replacement', 'Hello, world!', TRUE, TRUE],
      'complex regex pattern' => ['complex_regex.txt', 'Email: user@example.com', '/(\w+)@(\w+\.\w+)/', '[$1 AT $2]', 'Email: [user AT example.com]', TRUE, TRUE],
      'unicode content in file' => ['unicode.txt', 'Hello, 世界!', '世界', 'world', 'Hello, world!', TRUE, TRUE],
      'special characters' => ['special.txt', 'Price: $10.00 (tax: $1.50)', '$', '€', 'Price: €10.00 (tax: €1.50)', TRUE, TRUE],
      'no content change' => ['nochange.txt', 'Hello, world!', 'xyz', 'replacement', 'Hello, world!', TRUE, TRUE],
    ];
  }

  #[DataProvider('dataProviderReplaceContentCallback')]
  public function testReplaceContentCallback(string $content, callable $processor, string $expected): void {
    $result = File::replaceContentCallback($content, $processor);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderReplaceContentCallback(): array {
    return [
      'basic transformation' => [
        'Hello, world!',
        fn(string $content): string => str_replace('world', 'universe', $content),
        'Hello, universe!',
      ],
      'uppercase transformation' => [
        'hello world',
        fn(string $content): string => strtoupper($content),
        'HELLO WORLD',
      ],
      'complex transformation' => [
        "line1\nline2\nline3",
        fn(string $content): string => implode("\n", array_map(fn(string $line): string => '- ' . $line, explode("\n", $content))),
        "- line1\n- line2\n- line3",
      ],
      'no change' => [
        'unchanged content',
        fn(string $content): string => $content,
        'unchanged content',
      ],
      'empty content' => [
        '',
        fn(string $content): string => $content . 'added',
        '',
      ],
      'trim whitespace' => [
        '  content with spaces  ',
        fn(string $content): string => trim($content),
        'content with spaces',
      ],
      'regex replacement' => [
        'Hello, world!',
        fn(string $content): string => preg_replace('/world/', 'universe', $content) ?: $content,
        'Hello, universe!',
      ],
      'json processing' => [
        '{"name":"value"}',
        fn(string $content): string => json_encode(['name' => 'value', 'added' => TRUE]) ?: $content,
        '{"name":"value","added":true}',
      ],
      'multiline processing' => [
        "line1\r\nline2\r\nline3",
        fn(string $content): string => str_replace("\r\n", "\n", $content),
        "line1\nline2\nline3",
      ],
    ];
  }

  public function testReplaceContentCallbackInvalidCallable(): void {
    $this->expectException(\TypeError::class);
    // @phpstan-ignore-next-line
    File::replaceContentCallback('content', 'not_callable');
  }

  public function testReplaceContentCallbackInvalidReturnType(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Processor must return a string.');
    File::replaceContentCallback('content', fn($content): int => 123);
  }

  #[DataProvider('dataProviderReplaceContentCallbackInFile')]
  public function testReplaceContentCallbackInFile(string $filename, string $content, callable $processor, string $expected, bool $should_exist_before, bool $should_exist_after): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . $filename;

    if ($should_exist_before) {
      file_put_contents($file_path, $content);
    }

    File::replaceContentCallbackInFile($file_path, $processor);

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

  public static function dataProviderReplaceContentCallbackInFile(): array {
    return [
      'basic transformation' => [
        'test.txt',
        'Hello, world!',
        fn(string $content, string $file_path): string => str_replace('world', 'universe', $content),
        'Hello, universe!',
        TRUE,
        TRUE,
      ],
      'path-based processing' => [
        'config.json',
        '{"name":"test"}',
        fn(string $content, string $file_path): string => str_ends_with($file_path, '.json') ? str_replace('test', 'production', $content) : $content,
        '{"name":"production"}',
        TRUE,
        TRUE,
      ],
      'extension-based processing' => [
        'readme.md',
        'Content here',
        fn(string $content, string $file_path): string => str_ends_with($file_path, '.md') ? '# ' . $content : $content,
        '# Content here',
        TRUE,
        TRUE,
      ],
      'no change' => [
        'nochange.txt',
        'Hello, world!',
        fn(string $content, string $file_path): string => $content,
        'Hello, world!',
        TRUE,
        TRUE,
      ],
      'empty file' => [
        'empty.txt',
        '',
        fn(string $content, string $file_path): string => $content . 'added',
        '',
        TRUE,
        TRUE,
      ],
      'zero content file' => [
        'zero.txt',
        '0',
        fn(string $content, string $file_path): string => $content . '_processed',
        '0',
        TRUE,
        TRUE,
      ],
      'nonexistent file' => [
        'nonexistent.txt',
        '',
        fn(string $content, string $file_path): string => $content,
        '',
        FALSE,
        FALSE,
      ],
      'excluded image file' => [
        'image.jpg',
        'fake image content',
        fn(string $content, string $file_path): string => str_replace('fake', 'real', $content),
        'fake image content',
        TRUE,
        TRUE,
      ],
      'excluded png file' => [
        'photo.png',
        'png content here',
        fn(string $content, string $file_path): string => str_replace('png', 'jpeg', $content),
        'png content here',
        TRUE,
        TRUE,
      ],
    ];
  }

  public function testReplaceContentCallbackInFileInvalidCallable(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'content');

    $this->expectException(\TypeError::class);
    // @phpstan-ignore-next-line
    File::replaceContentCallbackInFile($file_path, 'not_callable');

    unlink($file_path);
  }

  public function testReplaceContentCallbackInFileCallbackException(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'content');

    $this->expectException(FileException::class);
    $this->expectExceptionMessageMatches('/Error processing file.*test\.txt.*Test exception/');

    File::replaceContentCallbackInFile($file_path, function (string $content, string $file_path): void {
      throw new \Exception('Test exception');
    });

    unlink($file_path);
  }

  public function testReplaceContentCallbackInFileInvalidReturnType(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'content');

    $this->expectException(FileException::class);
    $this->expectExceptionMessageMatches('/Error processing file.*Processor must return a string/');

    File::replaceContentCallbackInFile($file_path, function (string $content, string $file_path): int {
      return 123;
    });

    unlink($file_path);
  }

  public function testReplaceContentCallbackInDir(): void {
    $subdir = static::$sut . DIRECTORY_SEPARATOR . 'subdir';
    mkdir($subdir);

    $file1 = static::$sut . DIRECTORY_SEPARATOR . 'file1.txt';
    $file2 = $subdir . DIRECTORY_SEPARATOR . 'file2.txt';

    file_put_contents($file1, 'Hello, world!');
    file_put_contents($file2, 'Goodbye, world!');

    File::replaceContentCallbackInDir(static::$sut, function (string $content, string $file_path): string {
      return str_replace('world', 'universe', $content);
    });

    $this->assertSame('Hello, universe!', file_get_contents($file1));
    $this->assertSame('Goodbye, universe!', file_get_contents($file2));

    unlink($file1);
    unlink($file2);
    rmdir($subdir);
  }

  public function testReplaceContentCallbackInDirInvalidCallable(): void {
    $this->expectException(\TypeError::class);
    // @phpstan-ignore-next-line
    File::replaceContentCallbackInDir(static::$sut, 'not_callable');
  }

  public function testReplaceContentCallbackInDirWithFilePathUsage(): void {
    $subdir = static::$sut . DIRECTORY_SEPARATOR . 'subdir';
    mkdir($subdir);

    $file1 = static::$sut . DIRECTORY_SEPARATOR . 'config.json';
    $file2 = static::$sut . DIRECTORY_SEPARATOR . 'readme.md';
    $file3 = $subdir . DIRECTORY_SEPARATOR . 'other.txt';

    file_put_contents($file1, '{"env":"dev"}');
    file_put_contents($file2, 'Documentation');
    file_put_contents($file3, 'Regular content');

    File::replaceContentCallbackInDir(static::$sut, function (string $content, string $file_path): string {
      if (str_ends_with($file_path, '.json')) {
        return str_replace('dev', 'prod', $content);
      }
      if (str_ends_with($file_path, '.md')) {
        return '# ' . $content;
      }
      return strtoupper($content);
    });

    $this->assertSame('{"env":"prod"}', file_get_contents($file1));
    $this->assertSame('# Documentation', file_get_contents($file2));
    $this->assertSame('REGULAR CONTENT', file_get_contents($file3));

    unlink($file1);
    unlink($file2);
    unlink($file3);
    rmdir($subdir);
  }

  public function testReplaceContentCallbackInDirCallbackException(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'content');

    $this->expectException(FileException::class);
    $this->expectExceptionMessageMatches('/Error processing file.*test\.txt.*Callback error/');

    File::replaceContentCallbackInDir(static::$sut, function (string $content, string $file_path): string {
      throw new \Exception('Callback error');
    });

    unlink($file_path);
  }

}
