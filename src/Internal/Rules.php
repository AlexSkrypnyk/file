<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\Exception\RulesException;
use AlexSkrypnyk\File\File;

/**
 * Handles file matching rules and patterns.
 */
class Rules {

  /**
   * Patterns for files where only content should be ignored.
   */
  protected array $ignoreContent = [];

  /**
   * Patterns for files to skip.
   */
  protected array $skip = [];

  /**
   * Global patterns that apply everywhere.
   */
  protected array $global = [];

  /**
   * Patterns for files to explicitly include.
   */
  protected array $include = [];

  /**
   * Gets patterns for files where only content should be ignored.
   *
   * @return array
   *   Array of patterns.
   */
  public function getIgnoreContent(): array {
    return $this->ignoreContent;
  }

  /**
   * Gets patterns for files to skip.
   *
   * @return array
   *   Array of patterns.
   */
  public function getSkip(): array {
    return $this->skip;
  }

  /**
   * Gets global patterns that apply everywhere.
   *
   * @return array
   *   Array of patterns.
   */
  public function getGlobal(): array {
    return $this->global;
  }

  /**
   * Gets patterns for files to explicitly include.
   *
   * @return array
   *   Array of patterns.
   */
  public function getInclude(): array {
    return $this->include;
  }

  /**
   * Adds a pattern for files where only content should be ignored.
   *
   * @param string $pattern
   *   The pattern to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addIgnoreContent(string $pattern): static {
    $this->ignoreContent[] = $pattern;
    return $this;
  }

  /**
   * Adds a pattern for files to skip.
   *
   * @param string $pattern
   *   The pattern to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addSkip(string $pattern): static {
    $this->skip[] = $pattern;
    return $this;
  }

  /**
   * Adds a global pattern that applies everywhere.
   *
   * @param string $pattern
   *   The pattern to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addGlobal(string $pattern): static {
    $this->global[] = $pattern;
    return $this;
  }

  /**
   * Adds a pattern for files to explicitly include.
   *
   * @param string $pattern
   *   The pattern to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addInclude(string $pattern): static {
    $this->include[] = $pattern;
    return $this;
  }

  /**
   * Parse the rules content.
   *
   *  The syntax for the file is similar to .gitignore with addition of
   *  the content ignoring using ^ prefix:
   *  Comments start with #.
   *  file    Ignore file.
   *  dir/    Ignore directory and all subdirectories.
   *  dir/*   Ignore all files in directory, but not subdirectories.
   *  ^file   Ignore content changes in file, but not the file itself.
   *  ^dir/   Ignore content changes in all files and subdirectories, but check
   *          that the directory itself exists.
   *  ^dir/*  Ignore content changes in all files, but not subdirectories and
   *          check that the directory itself exists.
   *  !file   Do not ignore file.
   *  !dir/   Do not ignore directory, including all subdirectories.
   *  !dir/*  Do not ignore all files in directory, but not subdirectories.
   *  !^file  Do not ignore content changes in file.
   *  !^dir/  Do not ignore content changes in all files and subdirectories.
   *  !^dir/* Do not ignore content changes in all files, but not subdirs.
   *
   * @param string $content
   *   The content of the rules file.
   *
   * @return static
   *   The current instance.
   */
  public function parse(string $content): static {
    $lines = static::splitLines($content);

    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || $line[0] === '#') {
        continue;
      }
      elseif ($line[0] === '!') {
        $this->include[] = $line[1] === '^' ? substr($line, 2) : substr($line, 1);
      }
      elseif ($line[0] === '^') {
        $this->ignoreContent[] = substr($line, 1);
      }
      elseif (!str_contains($line, DIRECTORY_SEPARATOR)) {
        $this->global[] = $line;
      }
      else {
        $this->skip[] = $line;
      }
    }

    return $this;
  }

  /**
   * Creates a Rules instance from a file.
   *
   * @param string $file
   *   The path to the rules file.
   *
   * @return self
   *   A new Rules instance.
   *
   * @throws \Exception
   *   If the file does not exist or cannot be read.
   */
  public static function fromFile(string $file): self {
    if (!File::exists($file)) {
      throw new FileException(sprintf('File %s does not exist.', $file));
    }

    try {
      $content = File::read($file);
    }
    // @codeCoverageIgnoreStart
    catch (\Exception $exception) {
      throw new RulesException(sprintf('Failed to read the %s file.', $file), $exception->getCode(), $exception);
    }
    // @codeCoverageIgnoreEnd
    return (new self())->parse($content);
  }

  /**
   * Splits a string into lines.
   *
   * @param string $content
   *   The content to split.
   *
   * @return array
   *   Array of lines.
   *
   * @throws \AlexSkrypnyk\File\Exception\RulesException
   *   If the content cannot be split.
   */
  protected static function splitLines(string $content): array {
    $lines = preg_split('/(\r\n)|(\r)|(\n)/', $content);

    if ($lines === FALSE) {
      // @codeCoverageIgnoreStart
      throw new RulesException('Failed to split lines.');
      // @codeCoverageIgnoreEnd
    }

    return $lines;
  }

}
