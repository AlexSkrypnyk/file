<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\ContentFile;

/**
 * Interface for file objects with mutable content.
 *
 * This interface defines the contract for file objects that can hold
 * and modify content in memory, typically used for batch file processing.
 */
interface ContentFileInterface {

  /**
   * Gets the file content.
   *
   * @return string
   *   The file content.
   */
  public function getContent(): string;

  /**
   * Sets the file content.
   *
   * @param string|null $content
   *   The content to set, or NULL to clear.
   */
  public function setContent(?string $content): void;

  /**
   * Gets the full pathname of the file.
   *
   * @return string
   *   The full pathname.
   */
  public function getPathname(): string;

  /**
   * Gets the base name of the file.
   *
   * @param string $suffix
   *   Optional suffix to remove from the base name.
   *
   * @return string
   *   The base name.
   */
  public function getBasename(string $suffix = ''): string;

}
