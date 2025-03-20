<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\ExtendedSplFileInfo;
use AlexSkrypnyk\File\Internal\Index;
use AlexSkrypnyk\File\Internal\Rules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Index::class)]
#[CoversClass(ExtendedSplFileInfo::class)]
class IndexTest extends UnitTestBase {

  #[DataProvider('dataProviderIndexScan')]
  public function testIndexScan(?callable $rules, ?callable $before_match_content, array $expected): void {
    $dir = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal_advanced' . DIRECTORY_SEPARATOR . 'directory2');

    $rules = is_callable($rules) ? $rules() : $rules;

    $index = new Index($dir, $rules, $before_match_content);
    $this->callProtectedMethod($index, 'scan');

    $this->assertEquals($expected, array_keys($index->getFiles()));
  }

  public static function dataProviderIndexScan(): array {
    $defaults = [
      'd32f2_symlink_deep.txt',
      'dir1_flat/d1f1.txt',
      'dir1_flat/d1f1_symlink.txt',
      'dir1_flat/d1f2.txt',
      'dir2_flat/d2f1.txt',
      'dir2_flat/d2f2.txt',
      'dir3_subdirs/d3f1-ignored.txt',
      'dir3_subdirs/d3f2-ignored.txt',
      'dir3_subdirs/dir31/d31f1-ignored.txt',
      'dir3_subdirs/dir31/d31f2-ignored.txt',
      'dir3_subdirs/dir31/f3-new-file-ignore-everywhere.txt',
      'dir3_subdirs/dir32-unignored/d32f1.txt',
      'dir3_subdirs/dir32-unignored/d32f1_symlink.txt',
      'dir3_subdirs/dir32-unignored/d32f2-ignore-ext-only-dst.log',
      'dir3_subdirs/dir32-unignored/d32f2.txt',
      'dir3_subdirs/f3-new-file-ignore-everywhere.txt',
      'dir3_subdirs_symlink',
      'dir3_subdirs_symlink_ignored',
      'dir4_full_ignore/d4f1.txt',
      'dir5_content_ignore/d5f1-ignored-changed-content.txt',
      'dir5_content_ignore/d5f2-unignored-content.txt',
      'dir5_content_ignore/dir51/d51f1-changed-content.txt',
      'f1.txt',
      'f2.txt',
      'f2_symlink.txt',
      'f3-new-file-ignore-everywhere.txt',
      'f4-ignore-ext.log',
      'f5-new-file-ignore-ext.log',
    ];

    return [
      [NULL, NULL, $defaults],

      [NULL, fn(ExtendedSplFileInfo $file): null => NULL, $defaults],

      [NULL, fn(ExtendedSplFileInfo $file): true => TRUE, $defaults],

      [NULL, fn(ExtendedSplFileInfo $file): string => $file->getContent(), $defaults],

      [NULL, fn(ExtendedSplFileInfo $file): false => FALSE, []],

      [
        NULL,
        fn(ExtendedSplFileInfo $file): bool => str_contains($file->getContent(), 'f2l1'),
        [
          'd32f2_symlink_deep.txt',
          'dir1_flat/d1f2.txt',
          'dir2_flat/d2f2.txt',
          'dir3_subdirs/d3f2-ignored.txt',
          'dir3_subdirs/dir31/d31f2-ignored.txt',
          'dir3_subdirs/dir32-unignored/d32f2-ignore-ext-only-dst.log',
          'dir3_subdirs/dir32-unignored/d32f2.txt',
          'dir5_content_ignore/d5f2-unignored-content.txt',
          'f2.txt',
        ],
      ],

      [
        fn(): Rules => (new Rules())
          ->addGlobal('*.log')
          ->addGlobal('f3-new-file-ignore-everywhere.txt')
          ->addGlobal('dir3_subdirs_symlink_ignored')
          ->addSkip('dir2_flat/*')
          ->addSkip('dir3_subdirs/*')
          ->addSkip('dir4_full_ignore/')
          ->addInclude('dir3_subdirs/dir32-unignored/')
          ->addInclude('dir3_subdirs_symlink/dir32-unignored/')
          ->addInclude('dir5_content_ignore/d5f2-unignored-content.txt')
          ->addIgnoreContent('dir5_content_ignore/'),
        NULL,
        [
          'd32f2_symlink_deep.txt',
          'dir1_flat/d1f1.txt',
          'dir1_flat/d1f1_symlink.txt',
          'dir1_flat/d1f2.txt',
          'dir3_subdirs/dir31/d31f1-ignored.txt',
          'dir3_subdirs/dir31/d31f2-ignored.txt',
          'dir3_subdirs/dir32-unignored/d32f1.txt',
          'dir3_subdirs/dir32-unignored/d32f1_symlink.txt',
          'dir3_subdirs/dir32-unignored/d32f2.txt',
          'dir3_subdirs_symlink',
          'dir5_content_ignore/d5f1-ignored-changed-content.txt',
          'dir5_content_ignore/d5f2-unignored-content.txt',
          'dir5_content_ignore/dir51/d51f1-changed-content.txt',
          'f1.txt',
          'f2.txt',
          'f2_symlink.txt',
        ],
      ],
    ];
  }

  #[DataProvider('dataProviderIsPathMatchesPattern')]
  public function testIsPathMatchesPattern(string $path, string $pattern, bool $expected): void {
    $result = self::callProtectedMethod(Index::class, 'isPathMatchesPattern', [$path, $pattern]);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderIsPathMatchesPattern(): array {
    return [
      // Exact match.
      ['dir/file.txt', 'dir/file.txt', TRUE],

      // Directory match.
      ['dir/subdir/file.txt', 'dir/', TRUE],
      ['otherdir/file.txt', 'dir/', FALSE],

      // Direct child match.
      ['dir/file.txt', 'dir/*', TRUE],
      ['dir/subdir/file.txt', 'dir/*', FALSE],
      ['dir/another.txt', 'dir/*', TRUE],

      // Wildcard match.
      ['dir/file.txt', '*.txt', TRUE],
      ['dir/file.md', '*.txt', FALSE],
      // Should not match nested paths.
      ['dir/nested/file.txt', 'dir/*.txt', FALSE],

      // Pattern with a wildcard in the middle.
      ['dir/abc_file.txt', 'dir/abc_*.txt', TRUE],
      ['dir/xyz_file.txt', 'dir/abc_*.txt', FALSE],

      // Matching subdirectories.
      ['dir/subdir/file.txt', 'dir/subdir/*', TRUE],
      ['dir/anotherdir/file.txt', 'dir/subdir/*', FALSE],

      // Complex fnmatch pattern.
      ['dir/file.txt', 'dir/f*.txt', TRUE],
      ['dir/afile.txt', 'dir/f*.txt', FALSE],
    ];
  }

}
