<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

class Diff implements RenderInterface {

  protected ExtendedSplFileInfo $left;

  protected ExtendedSplFileInfo $right;

  public function setLeft(ExtendedSplFileInfo $file): static {
    $this->left = $file;

    return $this;
  }

  public function setRight(ExtendedSplFileInfo $file): static {
    $this->right = $file;

    return $this;
  }

  public function getLeft(): ExtendedSplFileInfo {
    return $this->left;
  }

  public function getRight(): ExtendedSplFileInfo {
    return $this->right;
  }

  public function existsLeft(): bool {
    return !empty($this->left);
  }

  public function existsRight(): bool {
    return !empty($this->right);
  }

  public function isSameContent(): bool {
    if (!$this->existsLeft() || !$this->existsRight()) {
      return FALSE;
    }

    $is_ignore_content = $this->left->isIgnoreContent() || $this->right->isIgnoreContent();
    $is_same_hash = $this->left->getHash() === $this->right->getHash();

    return $is_ignore_content || $is_same_hash;
  }

  /**
   * Returns content of the file (if they are the same) or the unified diff.
   */
  public function render(array $options = [], ?callable $renderer = NULL): ?string {
    return call_user_func($renderer ?? [static::class, 'doRender'], $this, $options);
  }

  protected static function doRender(Diff $diff, array $options = []): string {
    if ($diff->isSameContent()) {
      return $diff->getLeft()->getContent();
    }

    $src_content = $diff->getLeft()->getContent();
    $dst_content = $diff->getRight()->getContent();

    return (new Differ(new UnifiedDiffOutputBuilder('', TRUE)))->diff($src_content, $dst_content);
  }

}
