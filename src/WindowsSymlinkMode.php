<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File;

/**
 * How File::copy() recreates symlinks on Windows.
 *
 * PHP on Windows reads every symlink target as a resolved absolute path, so
 * the stored relative target is lost. Other platforms keep the stored target
 * in every mode.
 */
enum WindowsSymlinkMode: string {

  /**
   * Point each copied symlink at the resolved target in the source tree.
   */
  case Absolute = 'absolute';

  /**
   * Make a target inside the copied tree relative to the copied symlink.
   */
  case Relative = 'relative';

}
