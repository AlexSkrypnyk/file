<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\ExtendedSplFileInfo;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ExtendedSplFileInfo::class)]
class ExtendedSplFileInfoTest extends UnitTestCase {

  public function testConstructor(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $this->assertEquals($base_path, $file_info->getBasepath());
    $this->assertEquals('test content', $file_info->getContent());
    $this->assertNotNull($file_info->getHash());
  }

  #[DataProvider('dataProviderHash')]
  public function testHash(string $content, string $expected): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, $content);

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $this->assertEquals($expected, $file_info->getHash());
  }

  public static function dataProviderHash(): array {
    return [
      'basic content' => ['test content', md5('test content')],
      'empty content' => ['', md5('')],
      'content with newlines' => ["line1\nline2", md5("line1\nline2")],
      'content with spaces (preserved)' => ['  test  ', md5('  test  ')],
    ];
  }

  public function testSetGetBasepath(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $this->assertEquals($base_path, $file_info->getBasepath());

    $new_base_path = static::$sut . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR;
    mkdir($new_base_path, 0777, TRUE);
    $file_info->setBasepath($new_base_path);

    $this->assertEquals(rtrim($new_base_path, DIRECTORY_SEPARATOR), $file_info->getBasepath());
  }

  public function testGetPathFromBasepath(): void {
    $subdir = static::$sut . DIRECTORY_SEPARATOR . 'subdir';
    mkdir($subdir, 0777, TRUE);
    $file_path = $subdir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $this->assertEquals('subdir', $file_info->getPathFromBasepath());
  }

  public function testGetPathnameFromBasepath(): void {
    $subdir = static::$sut . DIRECTORY_SEPARATOR . 'subdir';
    mkdir($subdir, 0777, TRUE);
    $file_path = $subdir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $this->assertEquals('subdir' . DIRECTORY_SEPARATOR . 'test.txt', $file_info->getPathnameFromBasepath());
  }

  #[DataProvider('dataProviderIgnoreContent')]
  public function testIgnoreContent(bool $ignore_content, bool $expected): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $file_info->setIgnoreContent($ignore_content);
    $this->assertEquals($expected, $file_info->isIgnoreContent());
  }

  public static function dataProviderIgnoreContent(): array {
    return [
      'ignore content true' => [TRUE, TRUE],
      'ignore content false' => [FALSE, FALSE],
    ];
  }

  public function testIgnoreContentDefault(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $this->assertFalse($file_info->isIgnoreContent());
  }

  public function testSetContentWithSymlink(): void {
    $target_path = static::$sut . DIRECTORY_SEPARATOR . 'target.txt';
    file_put_contents($target_path, 'target content');

    $link_path = static::$sut . DIRECTORY_SEPARATOR . 'link.txt';
    symlink($target_path, $link_path);

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($link_path, $base_path);

    $this->assertEquals('target.txt', $file_info->getContent());
  }

  public function testSetContentWithNull(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;

    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $file_info->setContent(NULL);

    $this->assertEquals('test content', $file_info->getContent());
  }

  public function testStripBasepath(): void {
    $base_path = static::$sut;
    $full_path = $base_path . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file.txt';

    $result = self::callProtectedMethod(
      ExtendedSplFileInfo::class,
      'stripBasepath',
      [$base_path, $full_path]
    );

    $this->assertEquals('subdir' . DIRECTORY_SEPARATOR . 'file.txt', $result);

    $this->expectException(\Exception::class);
    self::callProtectedMethod(
      ExtendedSplFileInfo::class,
      'stripBasepath',
      ['/invalid/base', $full_path]
    );
  }

  #[DataProvider('dataProviderHashProtected')]
  public function testHashProtected(string $input, string $expected): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $result = self::callProtectedMethod($file_info, 'hash', [$input]);
    $this->assertEquals($expected, $result);
  }

  public static function dataProviderHashProtected(): array {
    return [
      'basic content' => ['test content', md5('test content')],
      'content with trailing spaces (preserved)' => [' test content ', md5(' test content ')],
      'content with leading spaces (preserved)' => ['   test content', md5('   test content')],
      'content with newlines (preserved)' => ["test\ncontent\r\nmore", md5("test\ncontent\r\nmore")],
      'content with carriage returns (preserved)' => ["test\rcontent", md5("test\rcontent")],
      'empty content' => ['', md5('')],
      'only spaces (preserved)' => ['   ', md5('   ')],
      'only newlines (preserved)' => ["\n\r\n\r", md5("\n\r\n\r")],
    ];
  }

}
