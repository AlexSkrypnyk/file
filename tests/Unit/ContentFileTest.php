<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\ContentFile\ContentFile;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ContentFile::class)]
final class ContentFileTest extends UnitTestCase {

  protected string $testTmpDir;

  #[\Override]
  protected function setUp(): void {
    $this->testTmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('content_file_test_', TRUE);
    mkdir($this->testTmpDir, 0777, TRUE);
  }

  #[\Override]
  protected function tearDown(): void {
    if (is_dir($this->testTmpDir)) {
      /** @var \RecursiveIteratorIterator<\RecursiveDirectoryIterator> $files */
      $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($this->testTmpDir, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
      );
      /** @var \SplFileInfo $file */
      foreach ($files as $file) {
        if ($file->isDir()) {
          rmdir((string) $file->getRealPath());
        }
        else {
          unlink((string) $file->getRealPath());
        }
      }
      rmdir($this->testTmpDir);
    }
  }

  public function testGetContentLoadsFromFile(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'Hello, World!');

    $content_file = new ContentFile($file_path);

    $this->assertSame('Hello, World!', $content_file->getContent());
  }

  public function testGetContentReturnsEmptyStringForNonExistentFile(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';

    $content_file = new ContentFile($file_path);

    $this->assertSame('', $content_file->getContent());
  }

  public function testGetContentReturnsEmptyStringForDirectory(): void {
    $dir_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'subdir';
    mkdir($dir_path, 0777);

    $content_file = new ContentFile($dir_path);

    $this->assertSame('', $content_file->getContent());
  }

  public function testGetContentCachesResult(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'cache_test.txt';
    file_put_contents($file_path, 'Original content');

    $content_file = new ContentFile($file_path);

    // First call loads from file.
    $first_call = $content_file->getContent();
    $this->assertSame('Original content', $first_call);

    // Modify the file after first load.
    file_put_contents($file_path, 'Modified content');

    // Second call should return cached content, not the modified file.
    $second_call = $content_file->getContent();
    $this->assertSame('Original content', $second_call);
  }

  public function testSetContentOverridesFileContent(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'override_test.txt';
    file_put_contents($file_path, 'File content');

    $content_file = new ContentFile($file_path);

    $content_file->setContent('New content');

    $this->assertSame('New content', $content_file->getContent());
  }

  public function testSetContentWithNull(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'null_test.txt';
    file_put_contents($file_path, 'Original content');

    $content_file = new ContentFile($file_path);

    // Set content first.
    $content_file->setContent('Some content');
    $this->assertSame('Some content', $content_file->getContent());

    // Set to NULL - should return empty string.
    $content_file->setContent(NULL);
    $this->assertSame('', $content_file->getContent());
  }

  public function testSetContentBeforeGetContent(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'set_before_get.txt';
    file_put_contents($file_path, 'File content');

    $content_file = new ContentFile($file_path);

    // Set content before ever calling getContent.
    $content_file->setContent('Preset content');

    // getContent should return the preset content, not the file content.
    $this->assertSame('Preset content', $content_file->getContent());
  }

  #[DataProvider('dataProviderContentVariations')]
  public function testContentVariations(string $content): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'variation_test.txt';
    file_put_contents($file_path, $content);

    $content_file = new ContentFile($file_path);

    $this->assertSame($content, $content_file->getContent());
  }

  public static function dataProviderContentVariations(): \Iterator {
    yield 'empty string' => [''];
    yield 'single character' => ['x'];
    yield 'zero string' => ['0'];
    yield 'whitespace only' => ['   '];
    yield 'newlines' => ["line1\nline2\nline3"];
    yield 'carriage returns' => ["line1\r\nline2\r\n"];
    yield 'tabs' => ["col1\tcol2\tcol3"];
    yield 'unicode content' => ['Héllo Wörld 你好'];
    yield 'special characters' => ['!@#$%^&*()_+-=[]{}|;:,.<>?'];
    yield 'null bytes' => ["before\0after"];
    yield 'long content' => [str_repeat('a', 10000)];
    yield 'multiline with mixed endings' => ["line1\nline2\r\nline3\rline4"];
  }

  public function testSetContentVariations(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'set_variations.txt';
    file_put_contents($file_path, 'initial');

    $content_file = new ContentFile($file_path);

    // Test setting empty string.
    $content_file->setContent('');
    $this->assertSame('', $content_file->getContent());

    // Test setting back to content.
    $content_file->setContent('restored');
    $this->assertSame('restored', $content_file->getContent());

    // Test setting unicode.
    $content_file->setContent('日本語テスト');
    $this->assertSame('日本語テスト', $content_file->getContent());
  }

  public function testGetPathnameReturnsFullPath(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'pathname_test.txt';
    file_put_contents($file_path, 'content');

    $content_file = new ContentFile($file_path);

    $this->assertSame($file_path, $content_file->getPathname());
  }

  public function testGetBasenameReturnsFilename(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'basename_test.txt';
    file_put_contents($file_path, 'content');

    $content_file = new ContentFile($file_path);

    $this->assertSame('basename_test.txt', $content_file->getBasename());
  }

  public function testGetBasenameWithSuffix(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'suffix_test.txt';
    file_put_contents($file_path, 'content');

    $content_file = new ContentFile($file_path);

    $this->assertSame('suffix_test', $content_file->getBasename('.txt'));
  }

  public function testMultipleSetContentCalls(): void {
    $file_path = $this->testTmpDir . DIRECTORY_SEPARATOR . 'multiple_sets.txt';
    file_put_contents($file_path, 'initial');

    $content_file = new ContentFile($file_path);

    $content_file->setContent('first');
    $this->assertSame('first', $content_file->getContent());

    $content_file->setContent('second');
    $this->assertSame('second', $content_file->getContent());

    $content_file->setContent('third');
    $this->assertSame('third', $content_file->getContent());
  }

}
