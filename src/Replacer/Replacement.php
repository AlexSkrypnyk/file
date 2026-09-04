<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Replacer;

use AlexSkrypnyk\File\Util\Strings;

/**
 * Represents a single replacement definition.
 *
 * A replacement consists of:
 * - A unique name for identification
 * - A matcher (regex pattern or closure for custom logic)
 * - A replacement string (used with regex matchers)
 * - Optional exclusion patterns to skip specific matches.
 *
 * @phpstan-consistent-constructor
 */
class Replacement implements ReplacementInterface {

  /**
   * Exclusion patterns and callbacks.
   *
   * @var array<string|\Closure>
   */
  protected array $exclusions = [];

  /**
   * Constructor.
   *
   * @param string $name
   *   Unique name for this replacement.
   * @param string|\Closure $matcher
   *   Regex pattern string or closure for custom matching.
   * @param string $replacement
   *   Replacement string (used only with regex matcher).
   */
  public function __construct(
    protected readonly string $name,
    protected readonly string|\Closure $matcher,
    protected readonly string $replacement = self::VERSION,
  ) {}

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(string $name, string|\Closure $matcher, string $replacement = self::VERSION): static {
    return new static($name, $matcher, $replacement);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getName(): string {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getMatcher(): string|\Closure {
    return $this->matcher;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getReplacement(): string {
    return $this->replacement;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function isCallback(): bool {
    return $this->matcher instanceof \Closure;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function apply(string &$content): bool {
    // Closures handle their own logic (no exclusion support).
    if ($this->matcher instanceof \Closure) {
      $original = $content;
      $content = ($this->matcher)($content);

      return $content !== $original;
    }

    $original = $content;
    $is_regex = self::isRegex($this->matcher);

    // Fast path: no exclusions.
    if ($this->exclusions === []) {
      if ($is_regex) {
        $result = preg_replace($this->matcher, $this->replacement, $content);
        $content = $result ?? $content;
      }
      else {
        $content = str_replace($this->matcher, $this->replacement, $content);
      }

      return $content !== $original;
    }

    // Slow path: check exclusions per match (only for regex matchers).
    if ($is_regex) {
      $content = preg_replace_callback(
          $this->matcher,
          function (array $matches): string {
            $match_text = $matches[0];

            if ($this->isExcluded($match_text)) {
              return $match_text;
            }

            $result = preg_replace($this->matcher, $this->replacement, $match_text);

            return $result ?? $match_text;
          },
          $content
        ) ?? $content;
    }
    elseif (!$this->isExcluded($this->matcher)) {
      // Plain string replacement has no per-match exclusions, so the needle
      // itself is checked for exclusion.
      $content = str_replace($this->matcher, $this->replacement, $content);
    }

    return $content !== $original;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function addExclusion(string|\Closure $exclusion): static {
    $this->exclusions[] = $exclusion;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getExclusions(): array {
    return $this->exclusions;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function clearExclusions(): static {
    $this->exclusions = [];

    return $this;
  }

  /**
   * Check if a string is a regex pattern.
   *
   * @param string $string
   *   The string to check.
   *
   * @return bool
   *   TRUE if the string is a regex pattern, FALSE otherwise.
   */
  public static function isRegex(string $string): bool {
    return Strings::isRegex($string);
  }

  /**
   * Check if matched text should be excluded.
   *
   * @param string $match
   *   The matched text to check.
   *
   * @return bool
   *   TRUE if the match should be excluded, FALSE otherwise.
   */
  protected function isExcluded(string $match): bool {
    foreach ($this->exclusions as $exclusion) {
      if ($exclusion instanceof \Closure) {
        if ($exclusion($match)) {
          return TRUE;
        }
      }
      elseif (self::isRegex($exclusion)) {
        if (preg_match($exclusion, $match)) {
          return TRUE;
        }
      }
      elseif (str_starts_with($exclusion, $match)) {
        // Exact string: exclude if the exclusion starts with the match.
        // This handles cases like match "127.0.0" with exclusion "127.0.0.1".
        return TRUE;
      }
    }

    return FALSE;
  }

}
