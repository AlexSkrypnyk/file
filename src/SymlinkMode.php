<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File;

/**
 * How File::copy() and File::copyPaths() recreate symlinks.
 *
 * PHP on Windows reads every symlink target as a resolved absolute path, so
 * the stored relative target is lost there. Linux and macOS read the stored
 * target.
 */
enum SymlinkMode: string {

  // Recreate each symlink with the target the platform reads: the stored
  // target on Linux and macOS, the resolved absolute target on Windows.
  case Native = 'native';

  // Make a target inside the copied tree relative to the copied symlink,
  // and a target outside the tree absolute, on every platform.
  case Relative = 'relative';

}
