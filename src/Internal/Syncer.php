<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

use AlexSkrypnyk\File\File;

/**
 * Handles file synchronization between directories.
 */
class Syncer {

  /**
   * Constructs a Syncer instance.
   *
   * @param \AlexSkrypnyk\File\Internal\Index $src_index
   *   The source index to sync from.
   */
  public function __construct(
    protected Index $src_index,
  ) {
  }

  /**
   * Sync files from one directory to another, respecting the .ignorecontent.
   *
   * @param string $dst
   *   Destination directory path.
   * @param int $permissions
   *   Directory permissions to use when creating directories.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function sync(string $dst, int $permissions = 0755, bool $copy_empty_dirs = FALSE): static {
    File::mkdir($dst, $permissions);

    foreach ($this->src_index->getFiles() as $file) {
      $absolute_src_path = $file->getPathname();
      $absolute_dst_path = $dst . DIRECTORY_SEPARATOR . $file->getPathnameFromBasepath();

      if ($file->isLink() || $file->isDir()) {
        File::copy($absolute_src_path, $absolute_dst_path, $permissions, $copy_empty_dirs);
      }
      else {
        File::dump($absolute_dst_path, $file->getContent());
      }
    }

    return $this;
  }

}
