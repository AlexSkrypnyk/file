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
    if ($string === '' || strlen($string) < 3) {
      return FALSE;
    }

    // Extract the first character as the delimiter.
    $delimiter = $string[0];

    if (!in_array($delimiter, ['/', '#', '~'])) {
      return FALSE;
    }

    $last_char = substr($string, -1);
    $before_last_char = substr($string, -2, 1);
    if (
      ($last_char !== $delimiter && !in_array($last_char, ['i', 'm', 's']))
      || ($before_last_char !== $delimiter && in_array($before_last_char, ['i', 'm', 's']))
    ) {
      return FALSE;
    }

    // Test the regex.
    $result = preg_match($string, '');
    return $result !== FALSE && preg_last_error() === PREG_NO_ERROR;
  }

}
