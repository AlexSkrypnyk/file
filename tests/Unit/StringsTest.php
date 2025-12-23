<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Util\Strings;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Strings::class)]
final class StringsTest extends UnitTestCase {

  #[DataProvider('dataProviderIsRegex')]
  public function testIsRegex(string $value, bool $expected): void {
    $this->assertSame($expected, Strings::isRegex($value));
  }

  public static function dataProviderIsRegex(): \Iterator {
    // Valid regular expressions with different delimiters.
    yield 'slash delimiter' => ['/pattern/', TRUE];
    yield 'slash with modifier' => ['/pattern/i', TRUE];
    yield 'slash with multiple modifiers' => ['/pattern/ims', TRUE];
    yield 'hash delimiter' => ['#pattern#', TRUE];
    yield 'hash with modifier' => ['#[a-z]*#i', TRUE];
    yield 'tilde delimiter' => ['~pattern~', TRUE];
    yield 'at delimiter' => ['@pattern@', TRUE];
    yield 'percent delimiter' => ['%pattern%', TRUE];
    yield 'complex pattern' => ['/^[a-z]$/', TRUE];
    yield 'quantifier pattern' => ['/a*/', TRUE];

    // Invalid delimiters.
    yield 'brace delimiter' => ['{\\d+}', FALSE];
    yield 'parenthesis delimiter' => ['(\\d+)', FALSE];
    yield 'angle bracket delimiter' => ['<[A-Z]{3,6}>', FALSE];

    // Invalid syntax.
    yield 'no delimiter' => ['^[a-z]$', FALSE];
    yield 'unclosed pattern' => ['/[a-z', FALSE];
    yield 'missing start delimiter' => ['[a-z]+/', FALSE];
    yield 'unclosed brace' => ['{[a-z]*', FALSE];
    yield 'mismatched brackets' => ['(a-z]', FALSE];

    // Edge cases.
    yield 'empty string' => ['', FALSE];
    yield 'just delimiters no pattern' => ['//', FALSE];
    yield 'single char' => ['/', FALSE];

    // Common strings that look like regex but are not.
    yield 'plain string' => ['hello', FALSE];
    yield 'version string' => ['1.0.0', FALSE];
    yield 'ip address' => ['127.0.0.1', FALSE];
    yield 'path like string' => ['/usr/bin', FALSE];
    yield 'url' => ['https://example.com', FALSE];
    yield 'glob pattern' => ['*/web/themes/contrib/*', FALSE];
    yield 'web path' => ['web/', FALSE];
    yield 'escaped web path' => ['web\/', FALSE];
    yield 'colon prefix' => [': web', FALSE];
    yield 'equals prefix' => ['=web', FALSE];
    yield 'exclamation prefix' => ['!web', FALSE];
    yield 'slash prefix only' => ['/web', FALSE];
  }

}
