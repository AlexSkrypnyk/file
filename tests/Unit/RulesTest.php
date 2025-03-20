<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Internal\Rules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Rules::class)]
class RulesTest extends UnitTestBase {

  #[DataProvider('dataProviderRulesFromFile')]
  public function testRulesFromFile(?string $content, array $expected): void {
    $file = static::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    if (!is_null($content)) {
      file_put_contents($file, $content);
    }
    else {
      $this->expectException(\Exception::class);
    }

    $rules = Rules::fromFile($file);
    $this->assertSame($expected['include'], $rules->getInclude());
    $this->assertSame($expected['content'], $rules->getIgnoreContent());
    $this->assertSame($expected['global'], $rules->getGlobal());
    $this->assertSame($expected['skip'], $rules->getSkip());

    unlink($file);
  }

  public static function dataProviderRulesFromFile(): array {
    $default = [
      'include' => [],
      'content' => [],
      'global' => [],
      'skip' => [],
    ];

    return [
      'non-existing file' => [
        NULL,
        $default,
      ],
      'empty file' => [
        '',
        $default,
      ],
      'only comments' => [
        "# This is a comment\n# Another comment",
        $default,
      ],
      'include rules' => [
        "!include-this\n!^content-only",
        [
          'include' => ['include-this', 'content-only'],
        ] + $default,
      ],
      'ignore content rules' => [
        "^ignore-content",
        [
          'content' => ['ignore-content'],
        ] + $default,
      ],
      'global rules' => [
        "global-pattern\nanother-pattern",
        [
          'global' => ['global-pattern', 'another-pattern'],
        ] + $default,
      ],
      'skip rules' => [
        "some/path/file.txt\nanother/path/",
        [
          'skip' => ['some/path/file.txt', 'another/path/'],
        ] + $default,
      ],
      'mixed rules' => [
        "!include-this\n!^content-only\n^ignore-content\nsome/path/file.txt\nglobal-pattern",
        [
          'include' => ['include-this', 'content-only'],
          'content' => ['ignore-content'],
          'global' => ['global-pattern'],
          'skip' => ['some/path/file.txt'],
        ],
      ],
    ];
  }

}
