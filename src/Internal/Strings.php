<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

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

    // Common regex delimiters.
    if (!in_array($delimiter, ['/', '#', '~', '@', '%'], TRUE)) {
      return FALSE;
    }

    // Must end with the delimiter (optionally followed by modifiers).
    if (!preg_match('/^' . preg_quote($delimiter, '/') . '.+' . preg_quote($delimiter, '/') . '[imsxADSUXJu]*$/', $string)) {
      return FALSE;
    }

    // Validate it's actually a working regex.
    return @preg_match($string, '') !== FALSE;
  }

}
