<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Functional;

use AlexSkrypnyk\File\File;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(File::class)]
#[CoversMethod(File::class, 'realpath')]
#[CoversMethod(File::class, 'absolute')]
#[CoversMethod(File::class, 'mkdir')]
#[CoversMethod(File::class, 'dir')]
#[CoversMethod(File::class, 'exists')]
#[CoversMethod(File::class, 'scandir')]
#[CoversMethod(File::class, 'read')]
#[CoversMethod(File::class, 'dirIsEmpty')]
final class FileStreamWrapperTest extends TestCase {

  protected vfsStreamDirectory $root;

  protected string $sandbox;

  protected string $originalCwd;

  #[\Override]
  protected function setUp(): void {
    $this->root = vfsStream::setup('run');

    $original_cwd = getcwd();
    $this->assertNotFalse($original_cwd, 'Failed to get current working directory');
    $this->originalCwd = $original_cwd;

    // A URL that loses its scheme separator resolves against the working
    // directory, so the tests run in a sandbox that is discarded afterwards.
    $this->sandbox = File::tmpdir();
    $this->assertTrue(chdir($this->sandbox), 'Failed to change directory');
  }

  #[\Override]
  protected function tearDown(): void {
    chdir($this->originalCwd);
    File::remove($this->sandbox);
  }

  public function testRealpath(): void {
    $url = vfsStream::url('run') . '/out/nested';

    $this->assertSame($url, File::realpath($url));
    $this->assertSame($url, File::absolute($url));
    $this->assertSame($url, File::absolute('nested', vfsStream::url('run') . '/out'));
    $this->assertSame(vfsStream::url('run') . '/other', File::realpath($url . '/../../other'));
  }

  public function testMkdir(): void {
    $url = vfsStream::url('run') . '/out/nested';

    $this->assertSame($url, File::mkdir($url));
    $this->assertTrue($this->root->hasChild('out/nested'));
    $this->assertDirectoryDoesNotExist(File::cwd() . DIRECTORY_SEPARATOR . 'vfs:');
    $this->assertTrue(File::dirIsEmpty($url));
  }

  public function testDirectoryContents(): void {
    $url = vfsStream::url('run') . '/out';

    File::mkdir($url);
    file_put_contents($url . '/file.txt', 'content');

    $this->assertSame($url, File::dir($url));
    $this->assertTrue(File::exists($url . '/file.txt'));
    $this->assertSame([$url . '/file.txt'], File::scandir(vfsStream::url('run')));
    $this->assertSame('content', File::read($url . '/file.txt'));
  }

}
