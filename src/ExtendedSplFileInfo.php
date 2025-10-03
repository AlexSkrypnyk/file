<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File;

use AlexSkrypnyk\File\Exception\FileException;

/**
 * Extended SplFileInfo class with additional file handling capabilities.
 */
class ExtendedSplFileInfo extends \SplFileInfo {

  /**
   * Marker used to flag when content should be ignored in comparison.
   */
  public const CONTENT_IGNORED_MARKER = 'content_ignored';

  /**
   * Base path used for relative path calculations.
   */
  protected string $basepath;

  /**
   * Content hash value.
   */
  protected ?string $hash = NULL;

  /**
   * File content.
   */
  protected ?string $content = NULL;

  /**
   * Whether content has been loaded.
   */
  protected bool $contentLoaded = FALSE;

  /**
   * Constructs a new ExtendedSplFileInfo object.
   *
   * @param string $filename
   *   The file name.
   * @param string $base
   *   The base path.
   * @param string|null $content
   *   Optional content to set.
   */
  public function __construct(string $filename, string $base, ?string $content = NULL) {
    parent::__construct($filename);

    $this->setBasepath($base);
    $this->setContent($content);
  }

  /**
   * Sets the base path.
   *
   * @param string $basepath
   *   The base path to set.
   */
  public function setBasepath(string $basepath): void {
    $this->basepath = rtrim($basepath, DIRECTORY_SEPARATOR);
  }

  /**
   * Gets the base path.
   *
   * @return string
   *   The base path.
   */
  public function getBasepath(): string {
    return $this->basepath;
  }

  /**
   * Gets the content hash.
   *
   * @return string|null
   *   The content hash.
   */
  public function getHash(): ?string {
    if (!$this->contentLoaded) {
      $this->loadContent();
    }
    return $this->hash;
  }

  /**
   * Gets the file content.
   *
   * @return string
   *   The file content.
   */
  public function getContent(): string {
    if (!$this->contentLoaded) {
      $this->loadContent();
    }
    return $this->content ?? '';
  }

  /**
   * Gets the path relative to the base path.
   *
   * @return string
   *   The path relative to the base path.
   */
  public function getPathFromBasepath(): string {
    return static::stripBasepath($this->getBasepath(), $this->getPath());
  }

  /**
   * Gets the pathname relative to the base path.
   *
   * @return string
   *   The pathname relative to the base path.
   */
  public function getPathnameFromBasepath(): string {
    return static::stripBasepath($this->getBasepath(), $this->getPathname());
  }

  /**
   * Checks if content should be ignored in comparison.
   *
   * @return bool
   *   TRUE if content should be ignored, FALSE otherwise.
   */
  public function isIgnoreContent(): bool {
    // If content is explicitly set, check it.
    // If not loaded yet, it can't be the ignore marker.
    return $this->contentLoaded && $this->content === static::CONTENT_IGNORED_MARKER;
  }

  /**
   * Sets whether content should be ignored in comparison.
   *
   * @param bool $ignore
   *   Whether to ignore the content.
   */
  public function setIgnoreContent(bool $ignore = TRUE): void {
    $this->setContent($ignore ? static::CONTENT_IGNORED_MARKER : NULL);
  }

  /**
   * Sets the file content.
   *
   * @param string|null $content
   *   The content to set, or NULL to load lazily.
   */
  public function setContent(?string $content): void {
    if (!is_null($content)) {
      $this->content = $content;
      $this->hash = $this->hash($this->content);
      $this->contentLoaded = TRUE;
    }
    else {
      // Defer content loading until needed.
      $this->contentLoaded = FALSE;
      $this->content = NULL;
      $this->hash = NULL;
    }
  }

  /**
   * Loads the file content.
   */
  protected function loadContent(): void {
    if ($this->contentLoaded) {
      return;
    }

    if ($this->isLink()) {
      $link_target = $this->getLinkTarget();
      // If the link target is absolute and within basepath, make it relative.
      if (str_starts_with($link_target, $this->basepath)) {
        $this->content = static::stripBasepath($this->basepath, $link_target);
      }
      else {
        $this->content = $link_target;
      }
    }
    else {
      $this->content = (string) file_get_contents($this->getRealPath());
    }

    $this->hash = $this->hash($this->content);
    $this->contentLoaded = TRUE;
  }

  /**
   * Creates a hash for the given content.
   *
   * @param string $content
   *   The content to hash.
   *
   * @return string
   *   A hash of the content.
   */
  protected function hash(string $content): string {
    return md5($content);
  }

  /**
   * Removes the base path from a path.
   *
   * @param string $basepath
   *   The base path to remove.
   * @param string $path
   *   The full path.
   *
   * @return string
   *   The path with base path removed.
   *
   * @throws \Exception
   *   If the path does not start with the base path.
   */
  protected static function stripBasepath(string $basepath, string $path): string {
    if (!str_starts_with($path, $basepath)) {
      throw new FileException(sprintf('Path %s does not start with basepath %s', $path, $basepath));
    }

    return ltrim(str_replace($basepath, '', $path), DIRECTORY_SEPARATOR);
  }

}
