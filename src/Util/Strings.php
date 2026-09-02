<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Util;

/**
 * Class with string manipulation utilities.
 */
class Strings {

  /**
   * Checks if a string is a valid regular expression.
   *
   * @param string $string
   *   The string to check.
   *
   * @return bool
   *   TRUE if the string is a valid regex, FALSE otherwise.
   */
  public static function isRegex(string $string): bool {
    if (strlen($string) < 2) {
      return FALSE;
    }

    $delimiter = $string[0];

    // Only common regex delimiters are accepted.
    if (!in_array($delimiter, ['/', '#', '~', '@', '%'], TRUE)) {
      return FALSE;
    }

    // The string must end with the delimiter, then optional modifiers.
    if (!preg_match('/^' . preg_quote($delimiter, '/') . '.+' . preg_quote($delimiter, '/') . '[imsxADSUXJu]*$/', $string)) {
      return FALSE;
    }

    // Validate that the string is a working regex.
    return @preg_match($string, '') !== FALSE;
  }

}
