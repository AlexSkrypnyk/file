<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\ExtendedSplFileInfo;
use AlexSkrypnyk\File\Internal\Diff;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Diff::class)]
class DiffTest extends UnitTestCase {

  public function testSetGetLeft(): void {
    $diff = new Diff();
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $file_info = new ExtendedSplFileInfo($file_path, static::$sut);

    $result = $diff->setLeft($file_info);

    $this->assertInstanceOf(Diff::class, $result);
    $this->assertSame($file_info, $diff->getLeft());
  }

  public function testSetGetRight(): void {
    $diff = new Diff();
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $file_info = new ExtendedSplFileInfo($file_path, static::$sut);

    $result = $diff->setRight($file_info);

    $this->assertInstanceOf(Diff::class, $result);
    $this->assertSame($file_info, $diff->getRight());
  }

  public function testExistsLeft(): void {
    $diff = new Diff();

    $this->assertFalse($diff->existsLeft());

    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');
    $file_info = new ExtendedSplFileInfo($file_path, static::$sut);

    $diff->setLeft($file_info);
    $this->assertTrue($diff->existsLeft());
  }

  public function testExistsRight(): void {
    $diff = new Diff();

    $this->assertFalse($diff->existsRight());

    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');
    $file_info = new ExtendedSplFileInfo($file_path, static::$sut);

    $diff->setRight($file_info);
    $this->assertTrue($diff->existsRight());
  }

  public function testIsSameContentWhenMissingFiles(): void {
    $diff = new Diff();

    // No files set.
    $this->assertFalse($diff->isSameContent());

    // Only left file set.
    $file_path = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');
    $file_info = new ExtendedSplFileInfo($file_path, static::$sut);

    $diff->setLeft($file_info);
    $this->assertFalse($diff->isSameContent());

    // Reset and set only right file.
    $diff = new Diff();
    $diff->setRight($file_info);
    $this->assertFalse($diff->isSameContent());
  }

  public function testIsSameContentWithSameContent(): void {
    $diff = new Diff();

    $file_path1 = static::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = static::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    file_put_contents($file_path1, 'test content');
    file_put_contents($file_path2, 'test content');

    $file_info1 = new ExtendedSplFileInfo($file_path1, static::$sut);
    $file_info2 = new ExtendedSplFileInfo($file_path2, static::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $this->assertTrue($diff->isSameContent());
  }

  public function testIsSameContentWithDifferentContent(): void {
    $diff = new Diff();

    $file_path1 = static::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = static::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    file_put_contents($file_path1, 'test content 1');
    file_put_contents($file_path2, 'test content 2');

    $file_info1 = new ExtendedSplFileInfo($file_path1, static::$sut);
    $file_info2 = new ExtendedSplFileInfo($file_path2, static::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $this->assertFalse($diff->isSameContent());
  }

  public function testIsSameContentWithIgnoreContent(): void {
    $diff = new Diff();

    $file_path1 = static::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = static::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    file_put_contents($file_path1, 'test content 1');
    file_put_contents($file_path2, 'test content 2');

    $file_info1 = new ExtendedSplFileInfo($file_path1, static::$sut);
    $file_info2 = new ExtendedSplFileInfo($file_path2, static::$sut);

    // Set left file to ignore content.
    $file_info1->setIgnoreContent(TRUE);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $this->assertTrue($diff->isSameContent());

    // Reset and set right file to ignore content.
    $diff = new Diff();
    $file_info1 = new ExtendedSplFileInfo($file_path1, static::$sut);
    $file_info2 = new ExtendedSplFileInfo($file_path2, static::$sut);
    $file_info2->setIgnoreContent(TRUE);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $this->assertTrue($diff->isSameContent());
  }

  public function testRender(): void {
    $diff = new Diff();

    $file_path1 = static::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = static::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    file_put_contents($file_path1, 'test content');
    file_put_contents($file_path2, 'test content');

    $file_info1 = new ExtendedSplFileInfo($file_path1, static::$sut);
    $file_info2 = new ExtendedSplFileInfo($file_path2, static::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    // Test with default renderer.
    $rendered = $diff->render();
    $this->assertEquals('test content', $rendered);

    // Test with custom renderer.
    $custom_renderer = function (Diff $diff, array $options = []): string {
      return 'Custom rendered content';
    };

    $rendered = $diff->render([], $custom_renderer);
    $this->assertEquals('Custom rendered content', $rendered);
  }

  public function testDoRender(): void {
    $diff = new Diff();

    $file_path1 = static::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = static::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    // Test with same content.
    file_put_contents($file_path1, 'test content');
    file_put_contents($file_path2, 'test content');

    $file_info1 = new ExtendedSplFileInfo($file_path1, static::$sut);
    $file_info2 = new ExtendedSplFileInfo($file_path2, static::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $result = self::callProtectedMethod(Diff::class, 'doRender', [$diff]);
    $this->assertEquals('test content', $result);

    // Test with different content.
    file_put_contents($file_path1, "line1\n");
    file_put_contents($file_path2, "line2\n");

    $file_info1 = new ExtendedSplFileInfo($file_path1, static::$sut);
    $file_info2 = new ExtendedSplFileInfo($file_path2, static::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $result = self::callProtectedMethod(Diff::class, 'doRender', [$diff]);
    assert(is_string($result));
    $this->assertStringContainsString('@@ -1 +1 @@', $result);
    $this->assertStringContainsString('-line1', $result);
    $this->assertStringContainsString('+line2', $result);
  }

}
