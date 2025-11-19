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
      'basic content' => ['test content', sha1('test content')],
      'empty content' => ['', sha1('')],
      'content with newlines' => ["line1\nline2", sha1("line1\nline2")],
      'content with spaces (preserved)' => ['  test  ', sha1('  test  ')],
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

  public function testLazyContentLoading(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;

    // Create file info with NULL content (lazy loading).
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    // Content should not be loaded yet - verify by using reflection.
    $reflection = new \ReflectionClass($file_info);
    $property = $reflection->getProperty('contentLoaded');
    $property->setAccessible(TRUE);
    $content_loaded = $property->getValue($file_info);
    $this->assertFalse($content_loaded, 'Content should not be loaded initially');

    // Access content triggers lazy loading.
    $content = $file_info->getContent();
    $this->assertEquals('test content', $content);

    // Verify content is now loaded.
    $content_loaded = $property->getValue($file_info);
    $this->assertTrue($content_loaded, 'Content should be loaded after getContent()');
  }

  public function testLazyHashLoading(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;

    // Create file info with NULL content (lazy loading).
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    // Hash should not be computed yet.
    $reflection = new \ReflectionClass($file_info);
    $property = $reflection->getProperty('hash');
    $property->setAccessible(TRUE);
    $hash = $property->getValue($file_info);
    $this->assertNull($hash, 'Hash should be NULL before content is loaded');

    // Access hash triggers content loading and hash computation.
    $computed_hash = $file_info->getHash();
    $this->assertEquals(sha1('test content'), $computed_hash);

    // Verify hash is now stored.
    $hash = $property->getValue($file_info);
    $this->assertNotNull($hash, 'Hash should be computed after getHash()');
  }

  public function testSetContentExplicitlySkipsLazyLoading(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'original content');

    $base_path = static::$sut;

    // Create file info and explicitly set content.
    $file_info = new ExtendedSplFileInfo($file_path, $base_path, 'explicit content');

    // Content should be loaded immediately.
    $reflection = new \ReflectionClass($file_info);
    $property = $reflection->getProperty('contentLoaded');
    $property->setAccessible(TRUE);
    $content_loaded = $property->getValue($file_info);
    $this->assertTrue($content_loaded, 'Content should be loaded when set explicitly');

    // Content should be the explicit value, not file content.
    $content = $file_info->getContent();
    $this->assertEquals('explicit content', $content);
  }

  public function testSymlinkRelativeTarget(): void {
    $target_path = 'target.txt';
    $target_full_path = static::$sut . DIRECTORY_SEPARATOR . $target_path;
    file_put_contents($target_full_path, 'target content');

    $link_path = static::$sut . DIRECTORY_SEPARATOR . 'link.txt';
    symlink($target_path, $link_path);

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($link_path, $base_path);

    // Should return the relative symlink target.
    $this->assertEquals('target.txt', $file_info->getContent());
  }

  public function testIgnoreContentReturnsFalseWhenNotLoaded(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;

    // Create file info with NULL content (lazy loading).
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    // isIgnoreContent should return FALSE when content not loaded.
    $this->assertFalse($file_info->isIgnoreContent());

    // Verify content was not loaded by the check.
    $reflection = new \ReflectionClass($file_info);
    $property = $reflection->getProperty('contentLoaded');
    $property->setAccessible(TRUE);
    $content_loaded = $property->getValue($file_info);
    $this->assertFalse($content_loaded, 'isIgnoreContent() should not trigger content loading');
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
      'basic content' => ['test content', sha1('test content')],
      'content with trailing spaces (preserved)' => [' test content ', sha1(' test content ')],
      'content with leading spaces (preserved)' => ['   test content', sha1('   test content')],
      'content with newlines (preserved)' => ["test\ncontent\r\nmore", sha1("test\ncontent\r\nmore")],
      'content with carriage returns (preserved)' => ["test\rcontent", sha1("test\rcontent")],
      'empty content' => ['', sha1('')],
      'only spaces (preserved)' => ['   ', sha1('   ')],
      'only newlines (preserved)' => ["\n\r\n\r", sha1("\n\r\n\r")],
    ];
  }

  public function testLoadContentEarlyReturn(): void {
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $base_path = static::$sut;
    $file_info = new ExtendedSplFileInfo($file_path, $base_path);

    // Use reflection to call loadContent directly.
    $reflection = new \ReflectionClass($file_info);
    $load_method = $reflection->getMethod('loadContent');
    $load_method->setAccessible(TRUE);

    // First call loads content.
    $load_method->invoke($file_info);
    $this->assertEquals('test content', $file_info->getContent());

    // Modify file after first load.
    file_put_contents($file_path, 'modified content');

    // Second call to loadContent should hit early return at line 169.
    $load_method->invoke($file_info);

    // Content should still be the original cached value.
    // This proves early return worked.
    $this->assertEquals('test content', $file_info->getContent(), 'loadContent() early return should prevent reloading');
  }

}
