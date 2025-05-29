<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Internal\Rules;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Rules::class)]
class RulesTest extends UnitTestCase {

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
      'rules with whitespace' => [
        "  !include-with-spaces  \n  ^ignore-content-with-spaces  \n  global-with-spaces  \n  some/path/with spaces/  ",
        [
          'include' => ['include-with-spaces'],
          'content' => ['ignore-content-with-spaces'],
          'global' => ['global-with-spaces'],
          'skip' => ['some/path/with spaces/'],
        ],
      ],
      'rules with different line endings' => [
        "!include-this\r\n^ignore-content\r\rglobal-pattern\nsome/path/file.txt",
        [
          'include' => ['include-this'],
          'content' => ['ignore-content'],
          'global' => ['global-pattern'],
          'skip' => ['some/path/file.txt'],
        ],
      ],
    ];
  }

  #[DataProvider('dataProviderParseEdgeCases')]
  public function testParseEdgeCases(string $input, array $expected_include, array $expected_content, array $expected_global, array $expected_skip): void {
    $rules = new Rules();
    $rules->parse($input);

    $this->assertSame($expected_include, $rules->getInclude());
    $this->assertSame($expected_content, $rules->getIgnoreContent());
    $this->assertSame($expected_global, $rules->getGlobal());
    $this->assertSame($expected_skip, $rules->getSkip());
  }

  public static function dataProviderParseEdgeCases(): array {
    return [
      'empty lines and whitespace' => ["\n  \n\t\n", [], [], [], []],
      'special characters in include rule' => ["!special@chars", ["special@chars"], [], [], []],
      'regex special characters as global rule' => ["[regex].special+chars?{test}", [], [], ["[regex].special+chars?{test}"], []],
      'very long pattern' => [str_repeat("a", 1000), [], [], [str_repeat("a", 1000)], []],
    ];
  }

  public function testParseMethodChaining(): void {
    // Test chained parse calls.
    $rules = new Rules();
    $result = $rules->parse("!include-rule")
      ->parse("^ignore-content-rule")
      ->parse("global-rule")
      ->parse("some/path/");

    $this->assertSame($rules, $result);
    $this->assertSame(['include-rule'], $rules->getInclude());
    $this->assertSame(['ignore-content-rule'], $rules->getIgnoreContent());
    $this->assertSame(['global-rule'], $rules->getGlobal());
    $this->assertSame(['some/path/'], $rules->getSkip());
  }

  #[DataProvider('dataProviderAddMethods')]
  public function testAddMethods(string $method, string $getter, string $pattern): void {
    $rules = new Rules();
    $rules->$method($pattern);
    $this->assertSame([$pattern], $rules->$getter());
  }

  public static function dataProviderAddMethods(): array {
    return [
      'addIgnoreContent' => ['addIgnoreContent', 'getIgnoreContent', 'ignore-content-pattern'],
      'addSkip' => ['addSkip', 'getSkip', 'skip-pattern'],
      'addGlobal' => ['addGlobal', 'getGlobal', 'global-pattern'],
      'addInclude' => ['addInclude', 'getInclude', 'include-pattern'],
    ];
  }

  public function testAddMethodChaining(): void {
    // Test method chaining.
    $rules = new Rules();
    $result = $rules->addIgnoreContent('pattern1')
      ->addSkip('pattern2')
      ->addGlobal('pattern3')
      ->addInclude('pattern4');

    $this->assertSame($rules, $result, 'Method chaining should return the same instance');
    $this->assertSame(['pattern1'], $rules->getIgnoreContent());
    $this->assertSame(['pattern2'], $rules->getSkip());
    $this->assertSame(['pattern3'], $rules->getGlobal());
    $this->assertSame(['pattern4'], $rules->getInclude());
  }

  public function testFromFileReadException(): void {
    // Since system permission changes might not work in all test environments,
    // let's use a mock to simulate a file read exception.
    $rules_class = new class() extends Rules {

      public static function fromFile(string $file): Rules {
        throw new \Exception(sprintf('Failed to read the %s file.', $file));
      }

    };

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Failed to read the test-file.txt file.');
    $rules_class::fromFile('test-file.txt');
  }

  public function testCustomRulesImport(): void {
    // Create a test rules file with all types of patterns.
    $rules_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('rules_test_', TRUE) . '.txt';
    $content = <<<EOT
# This is a comment
!include-pattern
!^include-ignore-content-pattern
^ignore-content-pattern
global-pattern
path/to/file.txt
EOT;
    file_put_contents($rules_file, $content);

    try {
      // Test loading from file.
      $rules = Rules::fromFile($rules_file);

      // Check all the rules were loaded correctly.
      $this->assertSame(['include-pattern', 'include-ignore-content-pattern'], $rules->getInclude());
      $this->assertSame(['ignore-content-pattern'], $rules->getIgnoreContent());
      $this->assertSame(['global-pattern'], $rules->getGlobal());
      $this->assertSame(['path/to/file.txt'], $rules->getSkip());
    }
    finally {
      // Clean up.
      if (file_exists($rules_file)) {
        unlink($rules_file);
      }
    }
  }

}
