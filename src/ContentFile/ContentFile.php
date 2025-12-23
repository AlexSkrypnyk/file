<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\ContentFile;

/**
 * File object with mutable content for batch processing.
 *
 * Extends SplFileInfo to provide file metadata while adding the ability
 * to hold and modify content in memory. Used for batch file processing
 * where content needs to be read, modified, and written back.
 */
class ContentFile extends \SplFileInfo implements ContentFileInterface {

  /**
   * File content.
   */
  protected ?string $content = NULL;

  /**
   * Whether content has been loaded from file.
   */
  protected bool $contentLoaded = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getContent(): string {
    if (!$this->contentLoaded && $this->content === NULL) {
      $real_path = $this->getRealPath();
      $this->content = $real_path !== FALSE && is_file($real_path) ? (string) file_get_contents($real_path) : '';
      $this->contentLoaded = TRUE;
    }

    return $this->content ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setContent(?string $content): void {
    $this->content = $content;
    $this->contentLoaded = TRUE;
  }

}
