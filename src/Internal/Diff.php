<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

use AlexSkrypnyk\File\ExtendedSplFileInfo;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * File difference implementation.
 *
 * Compares two files and provides access to diff information.
 */
class Diff implements RenderInterface {

  /**
   * The left (source) file.
   */
  protected ExtendedSplFileInfo $left;

  /**
   * The right (destination) file.
   */
  protected ExtendedSplFileInfo $right;

  /**
   * Sets the left (source) file.
   *
   * @param \AlexSkrypnyk\File\ExtendedSplFileInfo $file
   *   The file to set.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function setLeft(ExtendedSplFileInfo $file): static {
    $this->left = $file;

    return $this;
  }

  /**
   * Sets the right (destination) file.
   *
   * @param \AlexSkrypnyk\File\ExtendedSplFileInfo $file
   *   The file to set.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function setRight(ExtendedSplFileInfo $file): static {
    $this->right = $file;

    return $this;
  }

  /**
   * Gets the left (source) file.
   *
   * @return \AlexSkrypnyk\File\ExtendedSplFileInfo
   *   The left file.
   */
  public function getLeft(): ExtendedSplFileInfo {
    return $this->left;
  }

  /**
   * Gets the right (destination) file.
   *
   * @return \AlexSkrypnyk\File\ExtendedSplFileInfo
   *   The right file.
   */
  public function getRight(): ExtendedSplFileInfo {
    return $this->right;
  }

  /**
   * Checks if the left file exists.
   *
   * @return bool
   *   TRUE if the left file exists, FALSE otherwise.
   */
  public function existsLeft(): bool {
    return !empty($this->left);
  }

  /**
   * Checks if the right file exists.
   *
   * @return bool
   *   TRUE if the right file exists, FALSE otherwise.
   */
  public function existsRight(): bool {
    return !empty($this->right);
  }

  /**
   * Checks if the left and right files have the same content.
   *
   * Content is considered the same if:
   * - Both files exist, and
   * - Either the content is ignored for at least one file, or
   * - The content hashes match.
   *
   * @return bool
   *   TRUE if the content is the same, FALSE otherwise.
   */
  public function isSameContent(): bool {
    if (!$this->existsLeft() || !$this->existsRight()) {
      return FALSE;
    }

    $is_ignore_content = $this->left->isIgnoreContent() || $this->right->isIgnoreContent();
    if ($is_ignore_content) {
      return TRUE;
    }

    // Quick check for regular files: different sizes mean different content.
    // Skip for symlinks as getSize() may not work on symlink targets.
    if (!$this->left->isLink() && !$this->right->isLink()) {
      try {
        if ($this->left->getSize() !== $this->right->getSize()) {
          return FALSE;
        }
      }
      // @codeCoverageIgnoreStart
      catch (\RuntimeException $runtime_exception) {
        // If getSize() fails, fall through to hash comparison.
      }
      // @codeCoverageIgnoreEnd
    }

    // Check content hash.
    $is_same_hash = $this->left->getHash() === $this->right->getHash();

    return $is_same_hash;
  }

  /**
   * Returns content of the file (if they are the same) or the unified diff.
   */
  public function render(array $options = [], ?callable $renderer = NULL): ?string {
    return call_user_func($renderer ?? [static::class, 'doRender'], $this, $options);
  }

  /**
   * Default renderer for diff content.
   *
   * @param \AlexSkrypnyk\File\Internal\Diff $diff
   *   The diff to render.
   * @param array $options
   *   Rendering options.
   *
   * @return string
   *   The rendered diff.
   */
  protected static function doRender(Diff $diff, array $options = []): string {
    if ($diff->isSameContent()) {
      return $diff->getLeft()->getContent();
    }

    $src_content = $diff->getLeft()->getContent();
    $dst_content = $diff->getRight()->getContent();

    return (new Differ(new UnifiedDiffOutputBuilder('', TRUE)))->diff($src_content, $dst_content);
  }

}
