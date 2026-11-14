<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File;

use AlexSkrypnyk\File\ContentFile\ContentFile;
use AlexSkrypnyk\File\ContentFile\ContentFileInterface;
use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\Replacer\Replacement;
use AlexSkrypnyk\File\Replacer\ReplacementInterface;
use AlexSkrypnyk\File\Replacer\Replacer;
use AlexSkrypnyk\File\Task\Tasker;
use AlexSkrypnyk\File\Util\Strings;
use Symfony\Component\Filesystem\Filesystem;

/**
 * File manipulation utilities.
 */
class File {

  /**
   * Recreate each symlink with the target the platform reads.
   *
   * Linux and macOS read the stored target. Windows reads the resolved
   * absolute target, which points into the source tree.
   */
  public const SYMLINK_MODE_NATIVE = 'native';

  /**
   * Make symlink targets inside the copied tree relative, on every platform.
   *
   * A target outside the copied tree becomes absolute.
   */
  public const SYMLINK_MODE_RELATIVE = 'relative';

  /**
   * The shared Replacer instance.
   */
  protected static ?Replacer $replacer = NULL;

  /**
   * How copy() and copyPaths() recreate symlinks.
   */
  protected static string $symlinkMode = self::SYMLINK_MODE_NATIVE;

  /**
   * Get current working directory with absolute path.
   *
   * @return string
   *   Absolute path to current working directory.
   */
  public static function cwd(): string {
    $current_dir = getcwd();
    if ($current_dir === FALSE) {
      // @codeCoverageIgnoreStart
      throw new FileException('Unable to determine current working directory.');
      // @codeCoverageIgnoreEnd
    }
    return static::absolute($current_dir);
  }

  /**
   * Replacement for PHP's `realpath` resolves non-existing paths.
   *
   * The main difference is that it does not return FALSE on non-existing
   * paths.
   *
   * A path carrying a stream wrapper scheme, such as `vfs://run/out`, keeps
   * its scheme and only the part after it is normalised. Symlinks are not
   * resolved for such a path.
   *
   * @param string $path
   *   Path that needs to be resolved.
   *
   * @return string
   *   Resolved path.
   *
   * @see https://stackoverflow.com/a/29372360/712666
   */
  public static function realpath(string $path): string {
    $scheme = '';

    // A scheme is at least two characters long, so a Windows drive letter is
    // not mistaken for one.
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]+://#', $path, $matches) === 1) {
      $scheme = $matches[0];
      $path = substr($path, strlen($scheme));
    }

    // A stream URL is separated by a forward slash on every platform.
    $separator = $scheme === '' ? DIRECTORY_SEPARATOR : '/';

    $has_leading_slash = $path !== '' && $path[0] === '/';
    $unc = str_starts_with($path, '\\\\');

    // Detect a relative path and prepend the current working directory.
    if ($scheme === '' && !str_contains($path, ':') && !$has_leading_slash && !$unc) {
      $path = static::cwd() . DIRECTORY_SEPARATOR . $path;
      $has_leading_slash = $path[0] === '/';
    }

    // Resolve path parts (single dot, double dot and double delimiters).
    $path = str_replace(['/', '\\'], $separator, $path);
    $parts = array_filter(explode($separator, $path), static fn(string $part): bool => $part !== '');

    $absolutes = [];
    foreach ($parts as $part) {
      if ($part === '.') {
        continue;
      }
      if ($part === '..') {
        array_pop($absolutes);
      }
      else {
        $absolutes[] = $part;
      }
    }

    $path = implode($separator, $absolutes);
    // Put initial separator that could have been lost.
    $path = $has_leading_slash ? '/' . $path : $path;
    $path = $unc ? '\\\\' . $path : $path;
    $path = $scheme . $path;

    // readlink() does not accept a stream URL, and the temporary directory
    // prefix never matches one.
    if ($scheme !== '') {
      return $path;
    }

    if (function_exists('readlink') && file_exists($path) && is_link($path) > 0) {
      $path = readlink($path);

      if (!$path) {
        // @codeCoverageIgnoreStart
        throw new FileException(sprintf('Could not resolve symlink for path: %s', $path));
        // @codeCoverageIgnoreEnd
      }
    }

    if (str_starts_with($path, sys_get_temp_dir())) {
      $tmp_realpath = realpath(sys_get_temp_dir());
      if ($tmp_realpath) {
        $path = str_replace(sys_get_temp_dir(), $tmp_realpath, $path);
      }
    }

    return $path;
  }

  /**
   * Get absolute path for provided absolute or relative file.
   *
   * @param string $file
   *   File path to convert to absolute.
   * @param string|null $base
   *   Optional base directory. If not provided, current working directory is
   *   used.
   *
   * @return string
   *   Absolute file path.
   */
  public static function absolute(string $file, ?string $base = NULL): string {
    if ((new Filesystem())->isAbsolutePath($file)) {
      return static::realpath($file);
    }

    $base = $base ?: static::cwd();
    $base = static::realpath($base);
    $file = $base . DIRECTORY_SEPARATOR . $file;

    return static::realpath($file);
  }

  /**
   * Check if file or directory exists.
   *
   * @param string|array $files
   *   Path or array of paths to check.
   *
   * @return bool
   *   TRUE if file exists, FALSE otherwise.
   */
  public static function exists(string|array $files): bool {
    return (new Filesystem())->exists($files);
  }

  /**
   * Get absolute path for existing directory.
   *
   * @param string $directory
   *   Directory path.
   *
   * @return string
   *   Absolute directory path.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When directory does not exist.
   */
  public static function dir(string $directory): string {
    $directory = static::realpath($directory);

    if (!static::exists($directory)) {
      throw new FileException(sprintf('Directory "%s" does not exist.', $directory));
    }

    if (!is_dir($directory)) {
      throw new FileException(sprintf('Path "%s" is not a directory.', $directory));
    }

    return $directory;
  }

  /**
   * Creates a directory if it doesn't exist.
   *
   * @param string $directory
   *   Directory to create.
   * @param int $permissions
   *   Directory permissions.
   *
   * @return string
   *   Created directory path.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When directory cannot be created or is a file.
   */
  public static function mkdir(string $directory, int $permissions = 0777): string {
    $directory = static::absolute($directory);

    try {
      static::dir($directory);
    }
    catch (FileException) {
      if (static::exists($directory) && is_file($directory)) {
        throw new FileException(sprintf('Cannot create directory "%s": path exists and is a file.', $directory));
      }

      try {
        (new Filesystem())->mkdir($directory, $permissions);
        static::dir($directory);
      }
      // @codeCoverageIgnoreStart
      catch (\Exception $exception) {
        throw new FileException(sprintf('Unable to create directory "%s": %s', $directory, $exception->getMessage()), $exception->getCode(), $exception);
      }
      // @codeCoverageIgnoreEnd
    }

    return $directory;
  }

  /**
   * Check if directory is empty.
   *
   * @param string $directory
   *   Directory path to check.
   *
   * @return bool
   *   TRUE if directory is empty, FALSE otherwise.
   */
  public static function dirIsEmpty(string $directory): bool {
    $directory = static::dir($directory);
    return count(static::scandir($directory)) === 0;
  }

  /**
   * Create temporary directory.
   *
   * @param string|null $directory
   *   Optional base directory to create temporary directory in. If not
   *   provided, system temporary directory is used.
   * @param string $prefix
   *   Prefix for temporary directory name.
   * @param int $permissions
   *   Directory permissions.
   * @param int $max_attempts
   *   Maximum number of attempts to create unique directory.
   *
   * @return string
   *   Path to created temporary directory.
   *
   * @throws \InvalidArgumentException
   *   When prefix contains invalid characters.
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When directory cannot be created.
   */
  public static function tmpdir(?string $directory = NULL, string $prefix = 'tmp_', int $permissions = 0700, int $max_attempts = 1000): string {
    $directory = $directory ?: sys_get_temp_dir();
    $directory = rtrim($directory, DIRECTORY_SEPARATOR);
    $directory = static::mkdir($directory, $permissions);

    if (strpbrk($prefix, '\\/:*?"<>|') !== FALSE) {
      // @codeCoverageIgnoreStart
      throw new \InvalidArgumentException('Invalid prefix.');
      // @codeCoverageIgnoreEnd
    }
    $attempts = 0;

    do {
      $path = sprintf(
        '%s%s%s%s',
        $directory,
        DIRECTORY_SEPARATOR,
        $prefix,
        mt_rand(100000, mt_getrandmax())
      );
    } while (!static::mkdir($path, $permissions) && $attempts++ < $max_attempts);

    try {
      return static::dir($path);
    }
    // @codeCoverageIgnoreStart
    catch (FileException $file_exception) {
      throw new FileException(
        sprintf('Unable to create temporary directory "%s".', $path),
        $file_exception->getCode(),
        $file_exception
      );
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Find first path that matches a needle among provided paths.
   *
   * @param array|string $paths
   *   Path or array of paths to search in.
   * @param string|null $needle
   *   Optional string or regex pattern to match in file paths.
   *   Regex patterns must start with /, #, or ~ delimiter.
   *   If NULL, returns the first path found.
   *   Examples: 'text', '/\.php$/', '/pattern/i'.
   *
   * @return string|null
   *   First matching path or NULL if no matches found.
   */
  public static function findMatchingPath(array|string $paths, ?string $needle = NULL): ?string {
    $paths = is_array($paths) ? $paths : [$paths];

    foreach ($paths as $path) {
      $files = glob($path);

      if (empty($files)) {
        continue;
      }

      if (empty($needle)) {
        return reset($files);
      }

      foreach ($files as $file) {
        if (Strings::isRegex($needle)) {
          if (preg_match($needle, $file)) {
            return $file;
          }
        }
        elseif (str_contains($file, $needle)) {
          return $file;
        }
      }
    }

    return NULL;
  }

  /**
   * Copy file or directory.
   *
   * @param string $source
   *   Source file or directory path.
   * @param string $dest
   *   Destination file or directory path.
   * @param int $permissions
   *   Permissions to set on created directories.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   *
   * @return bool
   *   TRUE if copying was successful, FALSE otherwise.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When a symlink cannot be created.
   */
  public static function copy(string $source, string $dest, int $permissions = 0755, bool $copy_empty_dirs = FALSE): bool {
    $symlinks = [];
    $ret = static::copyPath($source, $dest, $permissions, $copy_empty_dirs, $source, $symlinks);
    static::createSymlinks($symlinks, $dest);

    return $ret;
  }

  /**
   * Copy selected paths of a directory, keeping their relative locations.
   *
   * Symlinks are created after every path is copied, and the source
   * directory is the tree that File::SYMLINK_MODE_RELATIVE keeps targets inside.
   * A symlink whose target is not among the copied paths is created
   * dangling.
   *
   * @param string $source
   *   Source directory path.
   * @param string $dest
   *   Destination directory path.
   * @param array<int, string> $paths
   *   Paths relative to $source.
   * @param int $permissions
   *   Permissions to set on created directories.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   *
   * @return bool
   *   TRUE if every path was copied, FALSE otherwise.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When a symlink cannot be created.
   */
  public static function copyPaths(string $source, string $dest, array $paths, int $permissions = 0755, bool $copy_empty_dirs = FALSE): bool {
    $symlinks = [];
    $ret = TRUE;

    foreach ($paths as $path) {
      $ret = static::copyPath($source . DIRECTORY_SEPARATOR . $path, $dest . DIRECTORY_SEPARATOR . $path, $permissions, $copy_empty_dirs, $source, $symlinks) && $ret;
    }

    static::createSymlinks($symlinks, $dest);

    return $ret;
  }

  /**
   * Copy file or directory that belongs to a copied tree.
   *
   * @param string $source
   *   Source file or directory path.
   * @param string $dest
   *   Destination file or directory path.
   * @param int $permissions
   *   Permissions to set on created directories.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   * @param string $root
   *   Root of the copied tree.
   * @param array<int, array{string, string, bool}> $symlinks
   *   Symlinks to create once the tree is copied, as the target, the
   *   destination path and whether the source target is a directory.
   *
   * @return bool
   *   TRUE if copying was successful, FALSE otherwise.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When the target of a symlink cannot be read.
   */
  protected static function copyPath(string $source, string $dest, int $permissions, bool $copy_empty_dirs, string $root, array &$symlinks): bool {
    static::mkdir(dirname($dest), $permissions);

    // PHP on Windows does not report a dangling symlink as a link.
    if (is_link($source) || (DIRECTORY_SEPARATOR === '\\' && !file_exists($source) && static::readWindowsSymlink($source) !== NULL)) {
      if (is_readable($dest)) {
        return TRUE;
      }

      $target = static::readSymlinkTarget($source, $root);

      if ($target === FALSE) {
        throw new FileException(sprintf('Unable to read the target of symlink "%s".', $source));
      }

      // Symlinks are created from within their directory, so a relative
      // destination is resolved while the working directory is unchanged.
      $symlinks[] = [$target, static::absolutePath($dest), static::isDirectorySymlink($source)];

      return TRUE;
    }

    if (is_file($source)) {
      try {
        (new Filesystem())->copy($source, $dest, TRUE);
        return TRUE;
      }
      // @codeCoverageIgnoreStart
      catch (\Exception) {
        return FALSE;
      }
      // @codeCoverageIgnoreEnd
    }

    if ($copy_empty_dirs) {
      static::mkdir($dest, $permissions);
    }

    $dir = dir($source);
    while ($dir && ($entry = $dir->read()) !== FALSE) {
      if ($entry === '.') {
        continue;
      }
      if ($entry === '..') {
        continue;
      }
      static::copyPath(sprintf('%s/%s', $source, $entry), sprintf('%s/%s', $dest, $entry), $permissions, FALSE, $root, $symlinks);
    }

    $dir && $dir->close();

    return TRUE;
  }

  /**
   * Create symlinks, retrying those whose target does not exist yet.
   *
   * PHP on Windows refuses to create a symlink to a missing target, so a
   * symlink to another symlink succeeds only on a later pass, and a symlink
   * to a target that was not copied needs a placeholder.
   *
   * @param array<int, array{string, string, bool}> $symlinks
   *   Symlinks as the target, the absolute symlink path and whether the
   *   source target is a directory.
   * @param string $dest
   *   Destination the copy writes to. Placeholders are created only inside
   *   it.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When a symlink cannot be created.
   */
  protected static function createSymlinks(array $symlinks, string $dest): void {
    if ($symlinks === []) {
      return;
    }

    $dest = realpath(static::absolutePath($dest)) ?: static::absolutePath($dest);
    $current_dir = static::cwd();
    $failed = [];

    try {
      while ($symlinks !== []) {
        $pending = [];

        foreach ($symlinks as $symlink) {
          if (!static::createSymlink($symlink[0], $symlink[1])) {
            $pending[] = $symlink;
          }
        }

        // A pass that creates nothing leaves only missing targets. The first
        // one gets a placeholder, which can unblock a symlink that points to
        // it on the next pass.
        if ($pending !== [] && count($pending) === count($symlinks)) {
          [$target, $link, $is_dir] = array_shift($pending);

          if (!static::createSymlinkWithPlaceholder($target, $link, $is_dir, $dest)) {
            $failed[] = sprintf('"%s" -> "%s"', $link, $target);
          }
        }

        $symlinks = $pending;
      }
    }
    finally {
      chdir($current_dir);
    }

    if ($failed !== []) {
      throw new FileException(sprintf('Unable to create symlinks: %s.', implode(', ', $failed)));
    }
  }

  /**
   * Create a symlink from within its directory.
   *
   * A relative target resolves against the current directory, so the
   * current directory changes to the directory of the symlink and stays
   * there.
   *
   * @param string $target
   *   Symlink target.
   * @param string $link
   *   Absolute symlink path.
   *
   * @return bool
   *   TRUE if the symlink was created, FALSE otherwise.
   */
  protected static function createSymlink(string $target, string $link): bool {
    if (!@chdir(dirname($link))) {
      // @codeCoverageIgnoreStart
      return FALSE;
      // @codeCoverageIgnoreEnd
    }

    try {
      (new Filesystem())->symlink($target, basename($link));
    }
    catch (\Exception) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Create a symlink to a missing target by standing in a placeholder.
   *
   * The placeholder has the type of the source target, so Windows creates a
   * directory symlink for a directory. The placeholder and any directories
   * created for it are removed afterwards, which leaves the symlink
   * dangling.
   *
   * @param string $target
   *   Symlink target.
   * @param string $link
   *   Absolute symlink path.
   * @param bool $is_dir
   *   Whether the source target is a directory.
   * @param string $dest
   *   Resolved destination directory the placeholder must stay inside.
   *
   * @return bool
   *   TRUE if the symlink was created, FALSE otherwise.
   */
  protected static function createSymlinkWithPlaceholder(string $target, string $link, bool $is_dir, string $dest): bool {
    $link_dir = realpath(dirname($link)) ?: dirname($link);
    $placeholder = static::realpath((new Filesystem())->isAbsolutePath($target) ? $target : $link_dir . DIRECTORY_SEPARATOR . $target);

    if (file_exists($placeholder) || is_link($placeholder) || !static::isPathInside($placeholder, $dest)) {
      return FALSE;
    }

    // The topmost missing directory is removed with the placeholder.
    $created_dir = NULL;
    for ($dir = dirname($placeholder); !is_dir($dir) && static::isPathInside($dir, $dest); $dir = dirname($dir)) {
      $created_dir = $dir;
    }

    $ret = FALSE;

    try {
      $made = $is_dir ? @mkdir($placeholder, 0777, TRUE) : (static::mkdirQuietly(dirname($placeholder)) && @touch($placeholder));
      $ret = $made && static::createSymlink($target, $link);
    }
    finally {
      if ($is_dir) {
        @rmdir($placeholder);
      }
      else {
        @unlink($placeholder);
      }

      if ($created_dir !== NULL) {
        (new Filesystem())->remove($created_dir);
      }
    }

    return $ret;
  }

  /**
   * Create a directory without raising a warning.
   *
   * @param string $dir
   *   Directory path.
   *
   * @return bool
   *   TRUE if the directory exists afterwards, FALSE otherwise.
   */
  protected static function mkdirQuietly(string $dir): bool {
    return is_dir($dir) || @mkdir($dir, 0777, TRUE);
  }

  /**
   * Check whether a symlink points to a directory.
   *
   * @param string $link
   *   Symlink path.
   *
   * @return bool
   *   TRUE if the symlink points to a directory, or is a dangling Windows
   *   directory symlink, FALSE otherwise.
   */
  protected static function isDirectorySymlink(string $link): bool {
    $resolved = realpath($link);

    if ($resolved !== FALSE) {
      return is_dir($resolved);
    }

    return DIRECTORY_SEPARATOR === '\\' && (static::readWindowsSymlink($link)[1] ?? FALSE);
  }

  /**
   * Read a symlink from the Windows directory listing of its directory.
   *
   * PHP on Windows reads a symlink by resolving its final path, so a dangling
   * symlink cannot be read through PHP. The listing of "dir /AL" holds the
   * stored target and the symlink type.
   *
   * @param string $link
   *   Symlink path.
   *
   * @return array{string, bool}|null
   *   The stored target and whether it is a directory symlink, or NULL if
   *   the path is not a symlink in the listing.
   *
   * @codeCoverageIgnore
   */
  protected static function readWindowsSymlink(string $link): ?array {
    if (!function_exists('exec')) {
      return NULL;
    }

    // Switch the console to UTF-8 so non-ASCII names list unchanged.
    exec(sprintf('chcp 65001 >nul 2>&1 & dir /AL %s 2>nul', escapeshellarg(dirname($link))), $output, $exit_code);

    return $exit_code === 0 ? static::parseWindowsSymlinkListing($output, basename($link)) : NULL;
  }

  /**
   * Find a symlink in the lines of a Windows "dir /AL" listing.
   *
   * A symlink line ends with "<SYMLINK> name [target]", or "<SYMLINKD>" for
   * a directory symlink. Junctions are not symlinks and are skipped.
   *
   * @param array<int, string> $lines
   *   Listing lines.
   * @param string $name
   *   Symlink name.
   *
   * @return array{string, bool}|null
   *   The stored target and whether it is a directory symlink, or NULL if
   *   the listing has no symlink with that name.
   */
  protected static function parseWindowsSymlinkListing(array $lines, string $name): ?array {
    $prefix = $name . ' [';

    foreach ($lines as $line) {
      if (preg_match('/<(SYMLINKD?)>\s+(.*)$/', rtrim($line), $matches) !== 1) {
        continue;
      }

      if (str_starts_with($matches[2], $prefix) && str_ends_with($matches[2], ']')) {
        return [substr($matches[2], strlen($prefix), -1), $matches[1] === 'SYMLINKD'];
      }
    }

    return NULL;
  }

  /**
   * Read the target to recreate a copied symlink with.
   *
   * On Windows, readlink() returns the resolved absolute target instead of
   * the stored one. In File::SYMLINK_MODE_RELATIVE, a target inside the
   * copied tree is made relative to the link on every platform, and a target
   * outside it becomes absolute.
   *
   * @param string $link
   *   Symlink path.
   * @param string $root
   *   Root of the copied tree.
   *
   * @return string|false
   *   Symlink target, or FALSE if it cannot be read.
   */
  protected static function readSymlinkTarget(string $link, string $root): string|false {
    $target = @readlink($link);

    // readlink() on Windows resolves the final path. realpath() still
    // resolves a file symlink that points to a directory, and the directory
    // listing holds the stored target of a dangling symlink.
    $target = $target === FALSE && DIRECTORY_SEPARATOR === '\\' ? (realpath($link) ?: (static::readWindowsSymlink($link)[0] ?? FALSE)) : $target;

    // A symlink copied on its own has no tree to stay inside.
    if ($target === FALSE || $link === $root || static::$symlinkMode !== self::SYMLINK_MODE_RELATIVE) {
      return $target;
    }

    return static::relativeSymlinkTarget(static::resolveSymlinkTarget($target, $link), dirname($link), $root, DIRECTORY_SEPARATOR === '\\');
  }

  /**
   * Resolve a symlink target to an absolute path.
   *
   * The directories of the target resolve through symlinks, and the last
   * segment stays as named, so a symlink to another symlink keeps pointing
   * at it. A target in a missing directory is only normalized.
   *
   * @param string $target
   *   Symlink target as read from the symlink.
   * @param string $link
   *   Symlink path.
   *
   * @return string
   *   Absolute target path.
   */
  protected static function resolveSymlinkTarget(string $target, string $link): string {
    $link_dir = realpath(dirname($link)) ?: dirname($link);
    $path = (new Filesystem())->isAbsolutePath($target) ? $target : $link_dir . DIRECTORY_SEPARATOR . $target;

    if (in_array(basename($path), ['.', '..'], TRUE)) {
      return realpath($path) ?: static::realpath($path);
    }

    $dir = realpath(dirname($path));

    return $dir === FALSE ? static::realpath($path) : $dir . DIRECTORY_SEPARATOR . basename($path);
  }

  /**
   * Convert a resolved symlink target inside a tree to a relative target.
   *
   * @param string $target
   *   Resolved absolute symlink target.
   * @param string $link_dir
   *   Directory holding the symlink.
   * @param string $root
   *   Root directory of the tree.
   * @param bool $ignore_case
   *   Whether paths compare case-insensitively, as on Windows.
   *
   * @return string
   *   Target relative to $link_dir if it is inside $root, or $target
   *   unchanged otherwise.
   */
  protected static function relativeSymlinkTarget(string $target, string $link_dir, string $root, bool $ignore_case): string {
    if (!static::isPathInside($target, realpath($root) ?: $root, $ignore_case)) {
      return $target;
    }

    $target_parts = explode('/', trim(static::toForwardSlashes($target), '/'));
    $link_parts = explode('/', trim(static::toForwardSlashes(realpath($link_dir) ?: $link_dir), '/'));

    $common = 0;
    while ($common < count($target_parts) && $common < count($link_parts) && static::pathPartsEqual($target_parts[$common], $link_parts[$common], $ignore_case)) {
      $common++;
    }

    $parts = array_merge(array_fill(0, count($link_parts) - $common, '..'), array_slice($target_parts, $common));

    return $parts === [] ? '.' : implode('/', $parts);
  }

  /**
   * Check whether a path is a directory or lies inside it.
   *
   * @param string $path
   *   Absolute path.
   * @param string $dir
   *   Absolute directory path.
   * @param bool|null $ignore_case
   *   Whether paths compare case-insensitively. Defaults to TRUE on Windows.
   *
   * @return bool
   *   TRUE if $path is $dir or lies inside it, FALSE otherwise.
   */
  protected static function isPathInside(string $path, string $dir, ?bool $ignore_case = NULL): bool {
    $ignore_case ??= DIRECTORY_SEPARATOR === '\\';
    $path = static::toForwardSlashes($path) . '/';
    $dir = rtrim(static::toForwardSlashes($dir), '/') . '/';

    return $ignore_case ? str_starts_with(strtolower($path), strtolower($dir)) : str_starts_with($path, $dir);
  }

  /**
   * Compare 2 path segments.
   *
   * @param string $a
   *   First segment.
   * @param string $b
   *   Second segment.
   * @param bool $ignore_case
   *   Whether the comparison ignores case.
   *
   * @return bool
   *   TRUE if the segments are equal, FALSE otherwise.
   */
  protected static function pathPartsEqual(string $a, string $b, bool $ignore_case): bool {
    return $ignore_case ? strcasecmp($a, $b) === 0 : $a === $b;
  }

  /**
   * Prefix a relative path with the current directory.
   *
   * @param string $path
   *   Path to make absolute.
   *
   * @return string
   *   Absolute path, with no symlinks resolved.
   */
  protected static function absolutePath(string $path): string {
    return (new Filesystem())->isAbsolutePath($path) ? $path : static::cwd() . DIRECTORY_SEPARATOR . $path;
  }

  /**
   * Copy file or directory if it exists.
   *
   * @param string $source
   *   Source file or directory path.
   * @param string $dest
   *   Destination file or directory path.
   * @param int $permissions
   *   Permissions to set on created directories.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   *
   * @return bool
   *   TRUE if copying was successful, FALSE otherwise.
   */
  public static function copyIfExists(string $source, string $dest, int $permissions = 0755, bool $copy_empty_dirs = FALSE): bool {
    if (static::exists($source)) {
      return static::copy($source, $dest, $permissions, $copy_empty_dirs);
    }
    return FALSE;
  }

  /**
   * Recursively scan directory for files.
   *
   * @param string $directory
   *   Directory to scan.
   * @param array<int, string> $ignore_paths
   *   Array of paths to ignore.
   * @param bool $include_dirs
   *   Include directories in the result.
   *
   * @return array<int, string>
   *   Array of discovered files.
   */
  public static function scandir(string $directory, array $ignore_paths = [], bool $include_dirs = FALSE): array {
    $discovered = [];

    try {
      $directory = static::dir($directory);
    }
    catch (FileException) {
      return [];
    }

    $files = scandir($directory);
    if ($files === FALSE) {
      // @codeCoverageIgnoreStart
      throw new FileException(sprintf('Failed to scan directory "%s".', $directory));
      // @codeCoverageIgnoreEnd
    }

    $paths = array_diff($files, ['.', '..']);

    if (empty($paths)) {
      return [];
    }

    $ignore_paths = array_map(static fn(mixed $ignore_path): string => static::toForwardSlashes((string) $ignore_path), $ignore_paths);

    foreach ($paths as $path) {
      $path = $directory . '/' . $path;
      $match_path = static::toForwardSlashes($path);

      foreach ($ignore_paths as $ignore_path) {
        // Exclude based on sub-path match.
        if (str_contains($match_path, $ignore_path)) {
          continue(2);
        }
      }

      if (is_dir($path)) {
        if ($include_dirs) {
          $discovered[] = $path;
        }
        $discovered = array_merge($discovered, static::scandir($path, $ignore_paths, $include_dirs));
      }
      else {
        $discovered[] = $path;
      }
    }

    return $discovered;
  }

  /**
   * Replace Windows directory separators with forward slashes.
   *
   * Paths built on Windows can mix both separators. Other platforms return
   * the path unchanged, since a backslash is a valid filename character there.
   *
   * @param string $path
   *   Path to convert.
   *
   * @return string
   *   Path with forward slashes on Windows, or the path unchanged elsewhere.
   */
  protected static function toForwardSlashes(string $path): string {
    return DIRECTORY_SEPARATOR === '\\' ? str_replace('\\', '/', $path) : $path;
  }

  /**
   * Remove directory recursively.
   *
   * @param string $directory
   *   Directory path to remove.
   */
  public static function rmdir(string $directory): void {
    (new Filesystem())->remove($directory);
  }

  /**
   * Remove directory recursively if empty.
   *
   * @param string $directory
   *   Directory path to remove if empty.
   */
  public static function rmdirIfEmpty(string $directory): void {
    if (is_dir($directory) && !is_link($directory) && static::dirIsEmpty($directory)) {
      static::rmdir($directory);
      static::rmdirIfEmpty(dirname($directory));
    }
  }

  /**
   * Remove file or directory.
   *
   * @param string|iterable<string> $files
   *   File or directory path, or iterable of paths to remove.
   */
  public static function remove(string|iterable $files): void {
    (new Filesystem())->remove($files);
  }

  /**
   * Write content to a file.
   *
   * @param string $file
   *   File path to write to.
   * @param string $content
   *   Content to write to the file.
   */
  public static function dump(string $file, string $content = ''): void {
    (new Filesystem())->dumpFile($file, $content);
  }

  /**
   * Append content to a file.
   *
   * @param string $file
   *   File path to append to.
   * @param string $content
   *   Content to append to the file.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When file does not exist or is not readable.
   */
  public static function append(string $file, string $content = ''): void {
    if (!static::exists($file) || !is_readable($file)) {
      throw new FileException(sprintf('File "%s" does not exist.', $file));
    }

    static::dump($file, static::read($file) . $content);
  }

  /**
   * Check if file contains a specific string or matches a pattern.
   *
   * @param string $file
   *   File path to check.
   * @param string $needle
   *   String or regex pattern to search for.
   *
   * @return bool
   *   TRUE if file contains the needle, FALSE otherwise.
   */
  public static function contains(string $file, string $needle): bool {
    if (!static::exists($file) || !is_readable($file)) {
      // @codeCoverageIgnoreStart
      return FALSE;
      // @codeCoverageIgnoreEnd
    }

    $content = static::read($file);
    if ($content === '' || $content === '0') {
      return FALSE;
    }

    if (Strings::isRegex($needle)) {
      return (bool) preg_match($needle, $content);
    }

    return str_contains($content, $needle);
  }

  /**
   * Find all files in directory containing a specific string.
   *
   * @param string $directory
   *   Directory to search in.
   * @param string $needle
   *   String to search for in files.
   * @param array $excluded
   *   Additional paths to exclude from search.
   *
   * @return array
   *   Array of files containing the needle.
   */
  public static function findContainingInDir(string $directory, string $needle, array $excluded = []): array {
    $contains = [];

    $files = static::scandir($directory, array_merge(static::ignoredPaths(), $excluded));
    foreach ($files as $filename) {
      if (static::contains($filename, $needle)) {
        $contains[] = $filename;
      }
    }

    return $contains;
  }

  /**
   * Rename files in directory by replacing part of the filename.
   *
   * @param string $directory
   *   Directory to search in.
   * @param string $search
   *   String to search for in filenames.
   * @param string $replace
   *   String to replace with.
   */
  public static function renameInDir(string $directory, string $search, string $replace): void {
    $files = static::scandir($directory, static::ignoredPaths());

    foreach ($files as $filename) {
      $new_filename = str_replace($search, $replace, (string) $filename);

      if ($filename != $new_filename) {
        $new_dir = dirname($new_filename);

        if (!is_dir($new_dir)) {
          static::mkdir($new_dir, 0777);
        }

        (new Filesystem())->rename($filename, $new_filename, TRUE);

        static::rmdirIfEmpty(dirname($filename));
      }
    }
  }

  /**
   * Replace content in all files in a directory.
   *
   * @param string $directory
   *   Directory to search in.
   * @param string|\AlexSkrypnyk\File\Replacer\ReplacementInterface $needle
   *   String, regex pattern, or ReplacementInterface instance.
   * @param string $replacement
   *   String to replace with (ignored when $needle is ReplacementInterface).
   */
  public static function replaceContentInDir(string $directory, string|ReplacementInterface $needle, string $replacement = ''): void {
    if (is_string($needle)) {
      $needle = Replacement::create('inline', $needle, $replacement);
    }

    static::getReplacer()
      ->addReplacement($needle)
      ->replaceInDir($directory, static::ignoredPaths());
  }

  /**
   * Replace content in a file.
   *
   * @param string $file
   *   File path to process.
   * @param string|\AlexSkrypnyk\File\Replacer\ReplacementInterface $needle
   *   String, regex pattern, or ReplacementInterface instance.
   * @param string $replacement
   *   String to replace with (ignored when $needle is ReplacementInterface).
   */
  public static function replaceContentInFile(string $file, string|ReplacementInterface $needle, string $replacement = ''): void {
    if (!static::exists($file) || !is_readable($file) || static::isExcluded($file)) {
      return;
    }

    $content = static::read($file);
    if ($content === '' || $content === '0') {
      return;
    }

    if (is_string($needle)) {
      $needle = Replacement::create('inline', $needle, $replacement);
    }

    $original = $content;

    static::getReplacer()
      ->addReplacement($needle)
      ->replace($content);

    if ($content !== $original) {
      static::dump($file, $content);
    }
  }

  /**
   * Remove lines containing a specific string or regex pattern from a string.
   *
   * @param string $content
   *   Content string to process.
   * @param string $needle
   *   String or regex pattern to search for in lines.
   *   Regex patterns must start with /, #, or ~ delimiter.
   *   Examples: 'text', '/^pattern/', '/regex/i'.
   *
   * @return string
   *   Processed content.
   */
  public static function removeLine(string $content, string $needle): string {
    if ($content === '') {
      return $content;
    }

    $line_ending = static::detectLineEnding($content);

    $lines = preg_split("/\r\n|\r|\n/", $content);
    if ($lines === FALSE) {
      // @codeCoverageIgnoreStart
      return $content;
      // @codeCoverageIgnoreEnd
    }

    if (Strings::isRegex($needle)) {
      $lines = array_filter($lines, fn(string $line): bool => !preg_match($needle, $line));
    }
    else {
      $lines = array_filter($lines, fn(string $line): bool => !str_contains($line, $needle));
    }

    return implode($line_ending, $lines);
  }

  /**
   * Remove lines containing a specific string or regex pattern from a file.
   *
   * @param string $file
   *   File path to process.
   * @param string $needle
   *   String or regex pattern to search for in lines.
   *   Regex patterns must start with /, #, or ~ delimiter.
   *   Examples: 'text', '/^pattern/', '/regex/i'.
   */
  public static function removeLineInFile(string $file, string $needle): void {
    if (!static::exists($file) || !is_readable($file) || static::isExcluded($file)) {
      return;
    }

    $content = static::read($file);
    $processed = static::removeLine($content, $needle);

    if ($processed !== $content) {
      static::dump($file, $processed);
    }
  }

  /**
   * Remove lines containing a specific string or regex pattern from all files.
   *
   * @param string $directory
   *   Directory to search in.
   * @param string $needle
   *   String or regex pattern to search for in lines.
   *   Regex patterns must start with /, #, or ~ delimiter.
   *   Examples: 'text', '/^pattern/', '/regex/i'.
   */
  public static function removeLineInDir(string $directory, string $needle): void {
    $files = static::scandir($directory, static::ignoredPaths());
    foreach ($files as $filename) {
      static::removeLineInFile($filename, $needle);
    }
  }

  /**
   * Remove tokens and optionally content between tokens from a file.
   *
   * @param string $file
   *   File path to process.
   * @param string $token_begin
   *   Begin token to search for.
   * @param string|null $token_end
   *   End token to search for. If not provided, same as begin token.
   * @param bool $with_content
   *   Whether to remove content between tokens.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When begin and end token counts don't match.
   */
  public static function removeTokenInFile(string $file, string $token_begin, ?string $token_end = NULL, bool $with_content = FALSE): void {
    if (static::isExcluded($file)) {
      return;
    }

    if (!static::exists($file) || !is_readable($file)) {
      return;
    }

    $content = static::read($file);
    if ($content === '' || $content === '0') {
      return;
    }

    try {
      $processed = static::removeToken($content, $token_begin, $token_end, $with_content);
      if ($processed !== $content) {
        static::dump($file, $processed);
      }
    }
    catch (FileException $file_exception) {
      throw new FileException(sprintf('Error processing file %s: %s', $file, $file_exception->getMessage()), $file_exception->getCode(), $file_exception);
    }
  }

  /**
   * Replace content in a string.
   *
   * @param string $content
   *   Content string to process.
   * @param string|\AlexSkrypnyk\File\Replacer\ReplacementInterface $needle
   *   String, regex pattern, or ReplacementInterface instance.
   * @param string $replacement
   *   String to replace with (ignored when $needle is ReplacementInterface).
   *
   * @return string
   *   Processed content.
   */
  public static function replaceContent(string $content, string|ReplacementInterface $needle, string $replacement = ''): string {
    if ($content === '') {
      return $content;
    }

    if (is_string($needle)) {
      $needle = Replacement::create('inline', $needle, $replacement);
    }

    static::getReplacer()
      ->addReplacement($needle)
      ->replace($content);

    return $content;
  }

  /**
   * Replace multiple consecutive empty lines with a single empty line.
   *
   * @param string $content
   *   The content to process.
   *
   * @return string
   *   The content with duplicated empty lines removed.
   */
  public static function collapseEmptyLines(string $content): string {
    if ($content === '') {
      return $content;
    }

    $line_ending = static::detectLineEnding($content);

    $normalized = str_replace(["\r\n", "\r"], "\n", $content);

    $had_whitespace_lines = (bool) preg_match('/^[ \t]+$/m', $normalized);
    $normalized = preg_replace('/^[ \t]+$/m', '', $normalized) ?? $content;

    if (preg_match('/^\n*$/', $normalized)) {
      return '';
    }

    $normalized = ltrim($normalized, "\n");

    $use_single_collapse = ($line_ending === "\r\n" && !$had_whitespace_lines);
    $pattern = $use_single_collapse ? "/\n{2,}/" : "/\n{3,}/";
    $replacement = $use_single_collapse ? "\n" : "\n\n";
    $normalized = preg_replace($pattern, $replacement, $normalized) ?? $content;

    $normalized = preg_replace("/\n{2,}$/", "\n", $normalized) ?? $content;

    return $line_ending !== "\n" ? str_replace("\n", $line_ending, $normalized) : $normalized;
  }

  /**
   * Replace multiple consecutive empty lines with a single empty line in file.
   *
   * @param string $file
   *   File path to process.
   */
  public static function collapseEmptyLinesInFile(string $file): void {
    if (!static::exists($file) || !is_readable($file) || static::isExcluded($file)) {
      return;
    }

    $content = static::read($file);
    $processed = static::collapseEmptyLines($content);

    if ($processed !== $content) {
      static::dump($file, $processed);
    }
  }

  /**
   * Replace multiple consecutive empty lines with a single empty line in dir.
   *
   * @param string $directory
   *   Directory to search in.
   */
  public static function collapseEmptyLinesInDir(string $directory): void {
    $files = static::scandir($directory, static::ignoredPaths());
    foreach ($files as $filename) {
      static::collapseEmptyLinesInFile($filename);
    }
  }

  /**
   * Remove tokens from content string (string version).
   *
   * @param string $content
   *   Content string to process.
   * @param string $token_begin
   *   Begin token to search for.
   * @param string|null $token_end
   *   End token to search for. If not provided, same as begin token.
   * @param bool $with_content
   *   Whether to remove content between tokens.
   *
   * @return string
   *   Processed content.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When begin and end token counts don't match.
   */
  public static function removeToken(string $content, string $token_begin, ?string $token_end = NULL, bool $with_content = FALSE): string {
    if ($content === '') {
      return $content;
    }

    $token_end ??= $token_begin;

    if ($token_begin !== $token_end) {
      $token_begin_count = preg_match_all('/' . preg_quote($token_begin, '/') . '/', $content);
      $token_end_count = preg_match_all('/' . preg_quote($token_end, '/') . '/', $content);
      if ($token_begin_count !== $token_end_count) {
        throw new FileException(sprintf('Invalid begin and end token count: begin is %s(%s), end is %s(%s).', $token_begin, $token_begin_count, $token_end, $token_end_count));
      }
    }

    $out = [];
    $within_token = FALSE;

    $lines = preg_split("/\r\n|\r|\n/", $content);
    if ($lines === FALSE) {
      // @codeCoverageIgnoreStart
      return $content;
      // @codeCoverageIgnoreEnd
    }

    $line_ending = static::detectLineEnding($content);

    foreach ($lines as $line) {
      if (str_contains($line, $token_begin)) {
        if ($with_content) {
          $within_token = TRUE;
        }
        continue;
      }
      if (str_contains($line, $token_end)) {
        if ($with_content) {
          $within_token = FALSE;
        }
        continue;
      }

      if ($with_content && $within_token) {
        continue;
      }

      $out[] = $line;
    }

    return implode($line_ending, $out);
  }

  /**
   * Remove tokens and content between tokens from all files in a directory.
   *
   * @param string $directory
   *   Directory to search in.
   * @param string|null $token
   *   Optional token name. If provided, removes content between '#;< token'
   *   and '#;> token'.
   *   If not provided, removes all '#;' tokens.
   */
  public static function removeTokenInDir(string $directory, ?string $token = NULL): void {
    $token_begin = '#;';
    $token_end = '#;';
    $with_content = FALSE;

    if (!is_null($token)) {
      $token_begin = '#;< ' . $token;
      $token_end = '#;> ' . $token;
      $with_content = TRUE;
    }

    $files = static::scandir($directory, static::ignoredPaths());
    foreach ($files as $filename) {
      static::removeTokenInFile($filename, $token_begin, $token_end, $with_content);
    }
  }

  /**
   * Get list of paths to ignore.
   *
   * @param array<int, string> $paths
   *   Additional paths to merge into the default list.
   *
   * @return array<int, string>
   *   Array of paths to ignore.
   */
  public static function ignoredPaths(array $paths = []): array {
    return array_merge([
      '/.git/',
      '/.idea/',
      '/vendor/',
      '/node_modules/',
    ], $paths);
  }

  /**
   * Check if file is excluded from processing.
   *
   * @param string $file
   *   Filename to check.
   *
   * @return bool
   *   TRUE if file is excluded, FALSE otherwise.
   */
  protected static function isExcluded(string $file): bool {
    $excluded_patterns = [
      '.+\.png',
      '.+\.jpg',
      '.+\.jpeg',
      '.+\.bmp',
      '.+\.tiff',
    ];

    return (bool) preg_match('/^(' . implode('|', $excluded_patterns) . ')$/', $file);
  }

  /**
   * Detect the dominant line ending used in content.
   *
   * Each kind of ending is counted and the most frequent one wins. A tie
   * resolves to LF, including when LF is absent from the content.
   *
   * @param string $content
   *   Content to inspect.
   *
   * @return string
   *   The detected line ending.
   */
  protected static function detectLineEnding(string $content): string {
    $crlf_count = substr_count($content, "\r\n");
    $lf_count = substr_count($content, "\n") - $crlf_count;
    $cr_count = substr_count($content, "\r") - $crlf_count;

    if ($crlf_count > $lf_count && $crlf_count > $cr_count) {
      return "\r\n";
    }

    if ($cr_count > $lf_count && $cr_count > $crlf_count) {
      return "\r";
    }

    return "\n";
  }

  /**
   * Read file contents.
   *
   * @param string $file
   *   File path to read.
   *
   * @return string
   *   File contents.
   */
  public static function read(string $file): string {
    return (new Filesystem())->readFile($file);
  }

  /**
   * Add a task to the directory task queue.
   *
   * @param callable $callback
   *   Callback function to execute.
   */
  public static function addDirectoryTask(callable $callback): void {
    static::getTasker()->addTask($callback, 'directory');
  }

  /**
   * Run all tasks for the directory batch.
   *
   * @param string $directory
   *   Directory to scan and process.
   */
  public static function runDirectoryTasks(string $directory): void {
    $iterator = function () use ($directory) {
      $files = static::scandir($directory, static::ignoredPaths());
      foreach ($files as $filename) {
        if (static::isExcluded($filename)) {
          continue;
        }

        $file = new ContentFile($filename);
        $original_content = $file->getContent();

        $processed_file = yield $file;

        if ($processed_file instanceof ContentFileInterface) {
          $new_content = $processed_file->getContent();

          if ($original_content !== $new_content) {
            static::dump($processed_file->getPathname(), $new_content);
          }
        }
      }
    };

    static::getTasker()
      ->setIterator($iterator, 'directory')
      ->process('directory');
  }

  /**
   * Clear tasks from the directory batch.
   */
  public static function clearDirectoryTasks(): void {
    static::getTasker()->clear('directory');
  }

  /**
   * Get the shared Tasker instance.
   *
   * @return \AlexSkrypnyk\File\Task\Tasker
   *   The shared tasker instance.
   */
  protected static function getTasker(): Tasker {
    static $tasker = NULL;

    $tasker ??= new Tasker();

    return $tasker;
  }

  /**
   * Set the Replacer instance.
   *
   * @param \AlexSkrypnyk\File\Replacer\Replacer|null $replacer
   *   The Replacer instance, or NULL to reset to default.
   */
  public static function setReplacer(?Replacer $replacer): void {
    static::$replacer = $replacer;
  }

  /**
   * Get the Replacer instance.
   *
   * If a Replacer has been explicitly set via setReplacer(), returns that
   * instance. Otherwise, returns a fresh Replacer instance for each call
   * to avoid accumulating replacements across operations.
   *
   * @return \AlexSkrypnyk\File\Replacer\Replacer
   *   The Replacer instance.
   */
  public static function getReplacer(): Replacer {
    return static::$replacer ?? Replacer::create();
  }

  /**
   * Reset the Replacer instance to default behavior.
   *
   * After calling this method, getReplacer() will return fresh instances
   * instead of the previously set Replacer.
   */
  public static function resetReplacer(): void {
    static::$replacer = NULL;
  }

  /**
   * Set how copy() and copyPaths() recreate symlinks.
   *
   * @param string $mode
   *   File::SYMLINK_MODE_NATIVE or File::SYMLINK_MODE_RELATIVE.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When the mode is not one of the symlink mode constants.
   */
  public static function setSymlinkMode(string $mode): void {
    if (!in_array($mode, [self::SYMLINK_MODE_NATIVE, self::SYMLINK_MODE_RELATIVE], TRUE)) {
      throw new FileException(sprintf('Unknown symlink mode "%s".', $mode));
    }

    static::$symlinkMode = $mode;
  }

  /**
   * Get how copy() and copyPaths() recreate symlinks.
   *
   * @return string
   *   File::SYMLINK_MODE_NATIVE or File::SYMLINK_MODE_RELATIVE.
   */
  public static function getSymlinkMode(): string {
    return static::$symlinkMode;
  }

  /**
   * Reset how copy() and copyPaths() recreate symlinks to the default.
   */
  public static function resetSymlinkMode(): void {
    static::$symlinkMode = self::SYMLINK_MODE_NATIVE;
  }

}
