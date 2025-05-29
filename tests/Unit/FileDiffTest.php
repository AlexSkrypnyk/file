<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\Comparer;
use AlexSkrypnyk\File\Internal\Diff;
use AlexSkrypnyk\File\Internal\Differ;
use AlexSkrypnyk\File\Internal\Index;
use AlexSkrypnyk\File\Internal\Patcher;
use AlexSkrypnyk\File\Internal\Syncer;
use AlexSkrypnyk\File\Tests\Traits\DirectoryAssertionsTrait;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

// @phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
#[CoversClass(File::class)]
#[CoversClass(Syncer::class)]
#[CoversClass(Patcher::class)]
#[CoversClass(Comparer::class)]
#[CoversClass(Diff::class)]
#[CoversClass(Differ::class)]
class FileDiffTest extends UnitTestCase {

  use DirectoryAssertionsTrait;

  #[DataProvider('dataProviderCompare')]
  public function testCompare(array $expected_diffs = []): void {
    $dir1 = File::dir($this->locationsFixtureDir() . DIRECTORY_SEPARATOR . 'directory1');
    $dir2 = File::dir($this->locationsFixtureDir() . DIRECTORY_SEPARATOR . 'directory2');

    $differ = File::compare($dir1, $dir2)->getDiffer();

    $absent_dir1 = $differ->getAbsentLeftDiffs();
    $absent_dir2 = $differ->getAbsentRightDiffs();
    $content = $differ->getContentDiffs(function (Diff $diff): array {
      return [
        'dir1' => $diff->getLeft()->getContent(),
        'dir2' => $diff->getRight()->getContent(),
      ];
    });

    $this->assertEquals($expected_diffs['absent_dir1'] ?? [], array_keys($absent_dir1));
    $this->assertEquals($expected_diffs['absent_dir2'] ?? [], array_keys($absent_dir2));
    $this->assertEquals($expected_diffs['content'] ?? [], $content);
  }

  public static function dataProviderCompare(): array {
    return [
      'files_equal' => [],
      'files_not_equal' => [
        [
          'absent_dir1' => [
            'f4.txt',
          ],
          'absent_dir2' => [
            'f3.txt',
          ],
          'content' => [
            'f2.txt' => [
              'dir1' => "f2l1\n",
              'dir2' => "f2l1-changed\n",
            ],
          ],
        ],
      ],
      'files_equal_ignorecontent' => [],
      'files_not_equal_ignorecontent' => [
        [
          'absent_dir1' => [
            'f4.txt',
          ],
          'absent_dir2' => [
            'f3.txt',
          ],
          'content' => [
            'f2.txt' => [
              'dir1' => "f2l1\n",
              'dir2' => "f2l1-changed\n",
            ],
          ],
        ],
      ],
      'files_equal_advanced' => [],
      'files_not_equal_advanced' => [
        [
          'absent_dir1' => [
            'dir2_flat-present-dst/d2f1.txt',
            'dir2_flat-present-dst/d2f2.txt',
            'dir3_subdirs/dir31/f4-new-file-notignore-everywhere.txt',
            'dir5_content_ignore/dir51/d51f2-new-file.txt',
            'f4-new-file-notignore-everywhere.txt',
          ],
          'absent_dir2' => [
            'd32f2_symlink_deep.txt',
            'dir1_flat/d1f1_symlink.txt',
            'dir1_flat/d1f3-only-src.txt',
            'dir3_subdirs/dir32-unignored/d32f1_symlink.txt',
            'dir3_subdirs_symlink',
            'f2_symlink.txt',
          ],
          'content' => [
            'dir3_subdirs/dir32-unignored/d32f2.txt' => [
              'dir1' => "d32f2l1\n",
              'dir2' => "d32f2l1-changed\n",
            ],
          ],
        ],
      ],
    ];
  }

  #[DataProvider('dataProviderCompareRender')]
  public function testCompareRender(array $expected): void {
    $dir1 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'directory1');
    $dir2 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'directory2');

    $content = File::compare($dir1, $dir2)->render();

    if ($expected === []) {
      $this->assertNull($content);
      return;
    }

    if (is_null($content)) {
      $this->fail('Expected content, but got NULL.');
    }

    foreach ($expected as $expected_line) {
      $this->assertStringContainsString($expected_line, $content);
    }
  }

  public static function dataProviderCompareRender(): array {
    return [
      'files_equal' => [
        [],
      ],

      'files_not_equal' => [
        [
          'Differences between directories',
          <<<ABSENT
Files absent in [left]:
  f4.txt
ABSENT,
          <<<ABSENT
Files absent in [right]:
  f3.txt
ABSENT,
          'Files that differ in content:',
          'f2.txt' => <<<DIFF
      --- DIFF START ---
      @@ -1 +1 @@
      -f2l1
      +f2l1-changed
      --- DIFF END ---
      DIFF,
        ],
      ],

      'files_equal_ignorecontent' => [
        [],
      ],

      'files_not_equal_ignorecontent' => [
        [
          'Differences between directories',
          <<<ABSENT
Files absent in [left]:
  f4.txt
ABSENT,
          <<<ABSENT
Files absent in [right]:
  f3.txt
ABSENT,
          'Files that differ in content:',
          'f2.txt' => <<<DIFF
      --- DIFF START ---
      @@ -1 +1 @@
      -f2l1
      +f2l1-changed
      --- DIFF END ---
      DIFF,
        ],
      ],

      'files_equal_advanced' => [
        [],
      ],

      'files_not_equal_advanced' => [
        [
          'Differences between directories',
          "Files absent in [left]:\n",
          <<<ABSENT
  dir2_flat-present-dst/d2f1.txt
  dir2_flat-present-dst/d2f2.txt
  dir3_subdirs/dir31/f4-new-file-notignore-everywhere.txt
  dir5_content_ignore/dir51/d51f2-new-file.txt
  f4-new-file-notignore-everywhere.txt
ABSENT,
          "Files absent in [right]:\n",
          <<<ABSENT
  d32f2_symlink_deep.txt
  dir1_flat/d1f1_symlink.txt
  dir1_flat/d1f3-only-src.txt
  dir3_subdirs/dir32-unignored/d32f1_symlink.txt
  dir3_subdirs_symlink
  f2_symlink.txt
ABSENT,
          'Files that differ in content:',
          'dir3_subdirs/dir32-unignored/d32f2.txt' => <<<DIFF
--- DIFF START ---
@@ -1 +1 @@
-d32f2l1
+d32f2l1-changed
--- DIFF END ---
DIFF,
        ],
      ],
    ];
  }

  #[DataProvider('dataProviderDiff')]
  public function testDiff(): void {
    $baseline = File::dir($this->locationsFixtureDir() . DIRECTORY_SEPARATOR . '/../baseline');
    $dst = File::dir($this->locationsFixtureDir() . DIRECTORY_SEPARATOR . 'result');

    File::diff($baseline, $dst, static::$sut);

    $expected = File::dir($this->locationsFixtureDir() . DIRECTORY_SEPARATOR . 'diff');

    $this->assertDirectoryEqualsDirectory($expected, static::$sut);
  }

  public static function dataProviderDiff(): array {
    return [
      'files_equal' => [],
      'files_not_equal' => [],
    ];
  }

  public function testSync(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');
    $expected = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');

    copy($expected . DIRECTORY_SEPARATOR . Index::IGNORECONTENT, static::$sut . DIRECTORY_SEPARATOR . Index::IGNORECONTENT);

    File::sync($src, static::$sut);

    $this->assertDirectoryEqualsDirectory($expected, static::$sut);
  }

  public function testSyncFile(): void {
    $this->expectException(FileException::class);
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');

    $dst = static::$sut . DIRECTORY_SEPARATOR . 'file.txt';
    touch($dst);

    File::sync($src, $dst);
  }

  #[DataProvider('dataProviderPatch')]
  public function testPatch(): void {
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . '/../baseline');
    $diff = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'diff');

    File::patch($baseline, $diff, static::$sut);

    $expected = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'result');

    $this->assertDirectoryEqualsDirectory($expected, static::$sut);
  }

  public static function dataProviderPatch(): array {
    return [
      'files_equal' => [],
      'files_not_equal' => [],
    ];
  }

}
