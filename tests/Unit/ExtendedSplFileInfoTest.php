<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Internal\ExtendedSplFileInfo;
use AlexSkrypnyk\PhpunitHelpers\UnitTestBase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ExtendedSplFileInfo::class)]
class ExtendedSplFileInfoTest extends UnitTestBase {

  public function testConstructor(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $this->assertEquals($base_path, $file_info->getBasepath());
    $this->assertEquals('test content', $file_info->getContent());
    $this->assertNotNull($file_info->getHash());
  }

  public function testGetHash(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $expected_hash = md5('test content');
    $this->assertEquals($expected_hash, $file_info->getHash());
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

  public function testIsSetIgnoreContent(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $this->assertFalse($file_info->isIgnoreContent());

    $file_info->setIgnoreContent(TRUE);
    $this->assertTrue($file_info->isIgnoreContent());

    $file_info->setIgnoreContent(FALSE);
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

  public function testHash(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    $result = self::callProtectedMethod($file_info, 'hash', ['test content']);
    $expected = md5('test content');

    $this->assertEquals($expected, $result);

    $result = self::callProtectedMethod($file_info, 'hash', [' test content ']);
    $expected = md5('test content');

    $this->assertEquals($expected, $result);
  }

}
