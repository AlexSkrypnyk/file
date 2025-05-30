<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

use AlexSkrypnyk\File\ExtendedSplFileInfo;
use AlexSkrypnyk\File\File;

/**
 * Collect and index of the files in the directory respecting the rules.
 *
 * @see Rules::parse()
 */
class Index {

  const IGNORECONTENT = '.ignorecontent';

  /**
   * Files indexed by the path from the base directory.
   *
   * @var array<string, \AlexSkrypnyk\File\ExtendedSplFileInfo>|null
   */
  protected ?array $files = NULL;

  /**
   * The rules to apply when indexing files.
   */
  protected Rules $rules;

  public function __construct(
    protected string $directory,
    ?Rules $rules = NULL,
    protected mixed $beforeMatchContent = NULL,
  ) {
    $this->rules = $rules ??
      (
      File::exists($directory . DIRECTORY_SEPARATOR . static::IGNORECONTENT)
        ? Rules::fromFile($directory . DIRECTORY_SEPARATOR . static::IGNORECONTENT)
        : new Rules()
      );
    $this->rules->addSkip(static::IGNORECONTENT)->addSkip('.git/');
  }

  /**
   * Gets the indexed files.
   *
   * If the files have not been indexed yet, this will trigger a scan.
   *
   * @param callable|null $cb
   *   Optional callback to transform each file.
   *
   * @return array
   *   Array of files indexed by path relative to base directory.
   */
  public function getFiles(?callable $cb = NULL): array {
    if (is_null($this->files)) {
      $this->scan();
    }

    $this->files = $this->files ?? [];

    if (is_callable($cb) && is_array($this->files)) {
      foreach ($this->files as $path => $file) {
        $this->files[$path] = $cb($file);
      }
    }

    return $this->files;
  }

  /**
   * Gets the directory being indexed.
   *
   * @return string
   *   The directory path.
   */
  public function getDirectory(): string {
    return $this->directory;
  }

  /**
   * Gets the rules used by this index.
   *
   * @return \AlexSkrypnyk\File\Internal\Rules
   *   The rules instance.
   */
  public function getRules(): Rules {
    return $this->rules;
  }

  /**
   * Scan files in directory respecting rules and optionally using a callback.
   */
  protected function scan(): static {
    $this->files = [];

    foreach ($this->iterator($this->directory) as $resource) {
      if (!$resource instanceof \SplFileInfo) {
        // @codeCoverageIgnoreStart
        continue;
        // @codeCoverageIgnoreEnd
      }

      // Skip directories, but not links to directories.
      if ($resource->isDir() && !$resource->isLink()) {
        continue;
      }

      // Skip links that point to non-existing files (broken links).
      if ($resource->isLink() && !$resource->getRealPath()) {
        continue;
      }

      $file = new ExtendedSplFileInfo($resource->getPathname(), $this->directory);

      foreach ($this->rules->getGlobal() as $pattern) {
        if (static::isPathMatchesPattern($file->getBasename(), $pattern)) {
          continue 2;
        }
      }

      $is_included = FALSE;
      foreach ($this->rules->getInclude() as $pattern) {
        if (static::isPathMatchesPattern($file->getPathnameFromBasepath(), $pattern)) {
          $is_included = TRUE;
          break;
        }
      }

      if (!$is_included) {
        foreach ($this->rules->getSkip() as $pattern) {
          if (static::isPathMatchesPattern($file->getPathnameFromBasepath(), $pattern)) {
            continue 2;
          }
        }
      }

      $is_ignore_content = FALSE;
      if (!$is_included) {
        foreach ($this->rules->getIgnoreContent() as $pattern) {
          if (static::isPathMatchesPattern($file->getPathnameFromBasepath(), $pattern)) {
            $is_ignore_content = TRUE;
            break;
          }
        }
      }

      if ($is_ignore_content) {
        $file->setIgnoreContent();
      }
      elseif ($file->isDir() && !$file->isLink()) {
        // @codeCoverageIgnoreStart
        $file->setIgnoreContent();
        // @codeCoverageIgnoreEnd
      }
      elseif (is_callable($this->beforeMatchContent)) {
        // Allow to skip files that do not match the content by returning FALSE
        // from the callback.
        $ret = call_user_func($this->beforeMatchContent, $file);
        if ($ret === FALSE) {
          continue;
        }
      }

      $this->files[$file->getPathnameFromBasepath()] = $file;
    }

    ksort($this->files);

    return $this;
  }

  /**
   * Checks if a path matches a pattern.
   *
   * Handles several types of patterns:
   * - Directory patterns ending with / (match all files inside directory)
   * - Direct child patterns with /* (match only files directly in directory)
   * - Standard file glob patterns (using fnmatch)
   *
   * @param string $path
   *   The path to check.
   * @param string $pattern
   *   The pattern to match against.
   *
   * @return bool
   *   TRUE if the path matches the pattern, FALSE otherwise.
   */
  protected static function isPathMatchesPattern(string $path, string $pattern): bool {
    // Match directory pattern (e.g., "dir/").
    if (str_ends_with($pattern, DIRECTORY_SEPARATOR)) {
      return str_starts_with($path, $pattern);
    }

    // Match direct children (e.g., "dir/*").
    if (str_contains($pattern, '/*')) {
      $parent_dir = rtrim($pattern, '/*') . DIRECTORY_SEPARATOR;

      return str_starts_with($path, $parent_dir) && substr_count($path, DIRECTORY_SEPARATOR) === substr_count($parent_dir, DIRECTORY_SEPARATOR);
    }

    // @phpcs:ignore Drupal.Functions.DiscouragedFunctions.Discouraged
    return fnmatch($pattern, $path);
  }

  /**
   * Get the iterator for the directory.
   *
   * @return \RecursiveIteratorIterator<\RecursiveDirectoryIterator>
   *   The iterator.
   */
  protected function iterator(string $directory): \RecursiveIteratorIterator {
    return new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
  }

}
