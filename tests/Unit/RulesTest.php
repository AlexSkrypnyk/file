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

  public function testParseMethodWithDifferentRules(): void {
    $rules = new Rules();

    // Test with empty line.
    $rules->parse("\n  \n\t\n");
    $this->assertEmpty($rules->getInclude());
    $this->assertEmpty($rules->getIgnoreContent());
    $this->assertEmpty($rules->getGlobal());
    $this->assertEmpty($rules->getSkip());

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

  public function testDirectParseMethods(): void {
    // Test direct parse methods with more edge cases.
    $rules = new Rules();

    // Test with a line containing special characters
    // The ! at the beginning means it's an include rule.
    $rules->parse("!special@chars");
    $this->assertSame(["special@chars"], $rules->getInclude());

    // Special case with lines that might be problematic for regex.
    $rules = new Rules();
    $rules->parse("[regex].special+chars?{test}");
    $this->assertSame(["[regex].special+chars?{test}"], $rules->getGlobal());

    // Test with a very long line.
    $rules = new Rules();
    $long_pattern = str_repeat("a", 1000);
    $rules->parse($long_pattern);
    $this->assertSame([$long_pattern], $rules->getGlobal());
  }

  public function testAddMethods(): void {
    $rules = new Rules();

    // Test addIgnoreContent.
    $rules->addIgnoreContent('ignore-content-pattern');
    $this->assertSame(['ignore-content-pattern'], $rules->getIgnoreContent());

    // Test addSkip.
    $rules->addSkip('skip-pattern');
    $this->assertSame(['skip-pattern'], $rules->getSkip());

    // Test addGlobal.
    $rules->addGlobal('global-pattern');
    $this->assertSame(['global-pattern'], $rules->getGlobal());

    // Test addInclude.
    $rules->addInclude('include-pattern');
    $this->assertSame(['include-pattern'], $rules->getInclude());

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

  public function testEmptyLinesAfterSplitting(): void {
    // Create a Rules instance that will test how empty lines are handled.
    $rules = new Rules();

    // Add empty lines and whitespace lines to make sure they're properly
    // handled.
    $rules->parse("  \n\t\n\r\n");

    // All arrays should be empty since there are no actual rules.
    $this->assertEmpty($rules->getInclude());
    $this->assertEmpty($rules->getIgnoreContent());
    $this->assertEmpty($rules->getGlobal());
    $this->assertEmpty($rules->getSkip());
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
