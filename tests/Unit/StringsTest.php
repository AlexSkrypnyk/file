<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Internal\Strings;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Strings::class)]
class StringsTest extends UnitTestCase {

  #[DataProvider('dataProviderIsRegex')]
  public function testIsRegex(string $value, mixed $expected): void {
    $this->assertEquals($expected, Strings::isRegex($value));
  }

  public static function dataProviderIsRegex(): array {
    return [
      ['', FALSE],

      // Valid regular expressions.
      ["/^[a-z]$/", TRUE],
      ["#[a-z]*#i", TRUE],

      // Invalid regular expressions (wrong delimiters or syntax).
      ["{\\d+}", FALSE],
      ["(\\d+)", FALSE],
      ["<[A-Z]{3,6}>", FALSE],
      ["^[a-z]$", FALSE],
      ["/[a-z", FALSE],
      ["[a-z]+/", FALSE],
      ["{[a-z]*", FALSE],
      ["(a-z]", FALSE],

      // Edge cases.
      // Valid, but '*' as delimiter would be invalid.
      ["/a*/", TRUE],
      // Empty string.
      ["", FALSE],
      // Just delimiters, no pattern.
      ["//", FALSE],

      ['web/', FALSE],
      ['web\/', FALSE],
      [': web', FALSE],
      ['=web', FALSE],
      ['!web', FALSE],
      ['/web', FALSE],
    ];
  }

}
