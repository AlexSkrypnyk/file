<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

use AlexSkrypnyk\File\File;

class Syncer {

  public function __construct(
    protected Index $src_index,
  ) {
  }

  /**
   * Sync files from one directory to another, respecting the .ignorecontent.
   */
  public function sync(string $dst, int $permissions = 0755, bool $copy_empty_dirs = FALSE): static {
    File::dir($dst, TRUE);

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
