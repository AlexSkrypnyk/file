<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

use AlexSkrypnyk\File\File;

class Rules {

  protected array $ignoreContent = [];

  protected array $skip = [];

  protected array $global = [];

  protected array $include = [];

  public function getIgnoreContent(): array {
    return $this->ignoreContent;
  }

  public function getSkip(): array {
    return $this->skip;
  }

  public function getGlobal(): array {
    return $this->global;
  }

  public function getInclude(): array {
    return $this->include;
  }

  public function addIgnoreContent(string $pattern): static {
    $this->ignoreContent[] = $pattern;
    return $this;
  }

  public function addSkip(string $pattern): static {
    $this->skip[] = $pattern;
    return $this;
  }

  public function addGlobal(string $pattern): static {
    $this->global[] = $pattern;
    return $this;
  }

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

  public static function fromFile(string $file): self {
    if (!File::exists($file)) {
      throw new \Exception(sprintf('File %s does not exist.', $file));
    }

    try {
      $content = File::read($file);
    }
    // @codeCoverageIgnoreStart
    catch (\Exception $exception) {
      throw new \Exception(sprintf('Failed to read the %s file.', $file), $exception->getCode(), $exception);
    }
    // @codeCoverageIgnoreEnd
    return (new self())->parse($content);
  }

  protected static function splitLines(string $content): array {
    $lines = preg_split('/(\r\n)|(\r)|(\n)/', $content);

    if ($lines === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \Exception('Failed to split lines.');
      // @codeCoverageIgnoreEnd
    }

    return $lines;
  }

}
