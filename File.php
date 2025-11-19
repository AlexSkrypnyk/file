<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File;

use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\Internal\Comparer;
use AlexSkrypnyk\File\Internal\Diff;
use AlexSkrypnyk\File\Internal\Index;
use AlexSkrypnyk\File\Internal\Patcher;
use AlexSkrypnyk\File\Internal\Rules;
use AlexSkrypnyk\File\Internal\Strings;
use AlexSkrypnyk\File\Internal\Syncer;
use AlexSkrypnyk\File\Internal\Tasker;
use Symfony\Component\Filesystem\Filesystem;

/**
 * File manipulation utilities.
 */
class File {

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
   * The main deference is that it does not return FALSE on non-existing
   * paths.
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
    // Whether $path is unix or not.
    $is_unix_path = $path === '' || $path[0] !== '/';
    $unc = str_starts_with($path, '\\\\');

    // Attempt to detect if path is relative in which case, add cwd.
    if (!str_contains($path, ':') && $is_unix_path && !$unc) {
      $path = static::cwd() . DIRECTORY_SEPARATOR . $path;
      if ($path[0] === '/') {
        $is_unix_path = FALSE;
      }
    }

    // Resolve path parts (single dot, double dot and double delimiters).
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), static function (string $part): bool {
      return $part !== '';
    });

    $absolutes = [];
    foreach ($parts as $part) {
      if ('.' === $part) {
        continue;
      }
      if ('..' === $part) {
        array_pop($absolutes);
      }
      else {
        $absolutes[] = $part;
      }
    }

    $path = implode(DIRECTORY_SEPARATOR, $absolutes);
    // Put initial separator that could have been lost.
    $path = $is_unix_path ? $path : '/' . $path;
    $path = $unc ? '\\\\' . $path : $path;

    // Resolve any symlinks.
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
    catch (FileException $file_exception) {
      // If path exists and is a file, throw exception immediately.
      if (static::exists($directory) && is_file($directory)) {
        throw new FileException(sprintf('Cannot create directory "%s": path exists and is a file.', $directory));
      }

      try {
        (new Filesystem())->mkdir($directory, $permissions);
        static::dir($directory);
      }
      // @codeCoverageIgnoreStart
      catch (\Exception $e) {
        throw new FileException(sprintf('Unable to create directory "%s": %s', $directory, $e->getMessage()), $e->getCode(), $e);
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
    return count(static::scandirRecursive($directory) ?: []) === 0;
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

      if (!empty($needle)) {
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
      else {
        return reset($files);
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
   */
  public static function copy(string $source, string $dest, int $permissions = 0755, bool $copy_empty_dirs = FALSE): bool {
    $filesystem = new Filesystem();
    $parent = dirname($dest);
    $parent = static::mkdir($parent, $permissions);

    // Note that symlink target must exist.
    if (is_link($source)) {
      // Changing dir symlink will be relevant to the current destination's file
      // directory.
      $cur_dir = static::cwd();

      chdir($parent);
      $ret = TRUE;

      if (!is_readable(basename($dest))) {
        $link = readlink($source);
        if ($link) {
          try {
            $filesystem->symlink($link, basename($dest));
          }
          // @codeCoverageIgnoreStart
          catch (\Exception $e) {
            $ret = FALSE;
          }
          // @codeCoverageIgnoreEnd
        }
      }

      chdir($cur_dir);

      return $ret;
    }

    if (is_file($source)) {
      try {
        $filesystem->copy($source, $dest, TRUE);
        return TRUE;
      }
      // @codeCoverageIgnoreStart
      catch (\Exception $e) {
        return FALSE;
      }
      // @codeCoverageIgnoreEnd
    }

    if ($copy_empty_dirs) {
      static::mkdir($dest, $permissions);
    }

    $dir = dir($source);
    while ($dir && FALSE !== $entry = $dir->read()) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }
      static::copy(sprintf('%s/%s', $source, $entry), sprintf('%s/%s', $dest, $entry), $permissions, FALSE);
    }

    $dir && $dir->close();

    return TRUE;
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
  public static function scandirRecursive(string $directory, array $ignore_paths = [], bool $include_dirs = FALSE): array {
    $discovered = [];

    try {
      $directory = static::dir($directory);
    }
    catch (FileException $file_exception) {
      return [];
    }

    $files = scandir($directory);
    if ($files === FALSE) {
      // @codeCoverageIgnoreStart
      throw new FileException(sprintf('Failed to scan directory "%s".', $directory));
      // @codeCoverageIgnoreEnd
    }

    $paths = array_diff($files, ['.', '..']);

    // If no files/directories remain after removing `.` and `..`, return
    // empty array.
    if (empty($paths)) {
      return [];
    }

    foreach ($paths as $path) {
      $path = $directory . '/' . $path;

      foreach ($ignore_paths as $ignore_path) {
        // Exclude based on sub-path match.
        if (str_contains($path, (string) $ignore_path)) {
          continue(2);
        }
      }

      if (is_dir($path)) {
        if ($include_dirs) {
          $discovered[] = $path;
        }
        $discovered = array_merge($discovered, static::scandirRecursive($path, $ignore_paths, $include_dirs));
      }
      else {
        $discovered[] = $path;
      }
    }

    return $discovered;
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
  public static function rmdirEmpty(string $directory): void {
    if (is_dir($directory) && !is_link($directory) && static::dirIsEmpty($directory)) {
      static::rmdir($directory);
      static::rmdirEmpty(dirname($directory));
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
  public static function containsInDir(string $directory, string $needle, array $excluded = []): array {
    $contains = [];

    $files = static::scandirRecursive($directory, array_merge(static::ignoredPaths(), $excluded));
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
    $files = static::scandirRecursive($directory, static::ignoredPaths());

    foreach ($files as $filename) {
      $new_filename = str_replace($search, $replace, (string) $filename);

      if ($filename != $new_filename) {
        $new_dir = dirname($new_filename);

        if (!is_dir($new_dir)) {
          static::mkdir($new_dir, 0777);
        }

        (new Filesystem())->rename($filename, $new_filename, TRUE);

        static::rmdirEmpty(dirname($filename));
      }
    }
  }

  /**
   * Replace content in all files in a directory.
   *
   * @param string $directory
   *   Directory to search in.
   * @param string $needle
   *   String to search for in file content.
   * @param string $replacement
   *   String to replace with.
   */
  public static function replaceContentInDir(string $directory, string $needle, string $replacement): void {
    $files = static::scandirRecursive($directory, static::ignoredPaths());
    foreach ($files as $filename) {
      static::replaceContentInFile($filename, $needle, $replacement);
    }
  }

  /**
   * Replace content in all files in a directory using a callback processor.
   *
   * @param string $directory
   *   Directory to search in.
   * @param callable $processor
   *   Callback function that receives file content and file path, returns
   *   processed content.
   *   Signature: function(string $content, string $file_path): string.
   */
  public static function replaceContentCallbackInDir(string $directory, callable $processor): void {
    $files = static::scandirRecursive($directory, static::ignoredPaths());
    foreach ($files as $filename) {
      static::replaceContentCallbackInFile($filename, $processor);
    }
  }

  /**
   * Replace content in a file.
   *
   * @param string $file
   *   File path to process.
   * @param string $needle
   *   String or regex pattern to search for.
   * @param string $replacement
   *   String to replace with.
   */
  public static function replaceContentInFile(string $file, string $needle, string $replacement): void {
    if (!static::exists($file) || !is_readable($file) || static::isExcluded($file)) {
      return;
    }

    $content = static::read($file);
    if ($content === '' || $content === '0') {
      return;
    }

    $replaced = static::replaceContent($content, $needle, $replacement);

    if ($replaced !== $content) {
      static::dump($file, $replaced);
    }
  }

  /**
   * Replace content in a file using a callback processor.
   *
   * @param string $file
   *   File path to process.
   * @param callable $processor
   *   Callback function that receives file content and file path, returns
   *   processed content.
   *   Signature: function(string $content, string $file_path): string.
   *
   * @throws \InvalidArgumentException
   *   When processor returns non-string.
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When callback execution fails.
   */
  public static function replaceContentCallbackInFile(string $file, callable $processor): void {
    if (!static::exists($file) || !is_readable($file) || static::isExcluded($file)) {
      return;
    }

    $content = static::read($file);
    if ($content === '' || $content === '0') {
      return;
    }

    try {
      $processed = $processor($content, $file);
      if (!is_string($processed)) {
        throw new \InvalidArgumentException('Processor must return a string.');
      }
    }
    catch (\Exception $exception) {
      throw new FileException(sprintf('Error processing file %s: %s', $file, $exception->getMessage()), $exception->getCode(), $exception);
    }

    if ($processed !== $content) {
      static::dump($file, $processed);
    }
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
  public static function removeLine(string $file, string $needle): void {
    if (!static::exists($file) || !is_readable($file) || static::isExcluded($file)) {
      return;
    }

    $content = static::read($file);

    $line_ending = "\n";
    if (str_contains($content, "\r\n")) {
      $line_ending = "\r\n";
    }
    elseif (str_contains($content, "\r")) {
      $line_ending = "\r";
    }

    $lines = preg_split("/\r\n|\r|\n/", $content);
    if ($lines === FALSE) {
      // @codeCoverageIgnoreStart
      return;
      // @codeCoverageIgnoreEnd
    }

    if (Strings::isRegex($needle)) {
      $lines = array_filter($lines, fn(string $line): bool => !preg_match($needle, $line));
    }
    else {
      $lines = array_filter($lines, fn(string $line): bool => !str_contains($line, $needle));
    }

    $content = implode($line_ending, $lines);

    static::dump($file, $content);
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
      // Re-throw with file context.
      throw new FileException(sprintf('Error processing file %s: %s', $file, $file_exception->getMessage()), $file_exception->getCode(), $file_exception);
    }
  }

  /**
   * Replace content in a string (string version).
   *
   * @param string $content
   *   Content string to process.
   * @param string $needle
   *   String or regex pattern to search for.
   * @param string $replacement
   *   String to replace with.
   *
   * @return string
   *   Processed content.
   */
  public static function replaceContent(string $content, string $needle, string $replacement): string {
    if ($content === '') {
      return $content;
    }

    if (Strings::isRegex($needle)) {
      $replaced = preg_replace($needle, $replacement, $content);
      return $replaced ?? $content;
    }
    else {
      return str_replace($needle, $replacement, $content);
    }
  }

  /**
   * Replace content in a string using a callback processor.
   *
   * @param string $content
   *   Content string to process.
   * @param callable $processor
   *   Callback function that receives content and returns processed content.
   *   Signature: function(string $content): string.
   *
   * @return string
   *   Processed content.
   *
   * @throws \InvalidArgumentException
   *   When processor returns non-string.
   */
  public static function replaceContentCallback(string $content, callable $processor): string {
    if ($content === '') {
      return $content;
    }

    $result = $processor($content);

    if (!is_string($result)) {
      throw new \InvalidArgumentException('Processor must return a string.');
    }

    return $result;
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
  public static function collapseRepeatedEmptyLines(string $content): string {
    if ($content === '') {
      return $content;
    }

    // Detect dominant line ending - simplified logic.
    $crlf_count = substr_count($content, "\r\n");
    $lf_count = substr_count($content, "\n") - $crlf_count;
    $cr_count = substr_count($content, "\r") - $crlf_count;

    $line_ending = "\n";
    if ($crlf_count > $lf_count && $crlf_count > $cr_count) {
      $line_ending = "\r\n";
    }
    elseif ($cr_count > $lf_count && $cr_count > $crlf_count) {
      $line_ending = "\r";
    }

    // Normalize line endings temporarily to \n.
    $normalized = str_replace(["\r\n", "\r"], "\n", $content);

    // Check for whitespace-only lines and replace with empty lines.
    $had_whitespace_lines = (bool) preg_match('/^[ \t]+$/m', $normalized);
    $normalized = preg_replace('/^[ \t]+$/m', '', $normalized) ?? $content;

    // Handle content that's only newlines.
    if (preg_match('/^\n*$/', $normalized)) {
      return "";
    }

    // Remove leading newlines.
    $normalized = ltrim($normalized, "\n");

    // Collapse consecutive newlines - unified logic.
    $use_single_collapse = ($line_ending === "\r\n" && !$had_whitespace_lines);
    $pattern = $use_single_collapse ? "/\n{2,}/" : "/\n{3,}/";
    $replacement = $use_single_collapse ? "\n" : "\n\n";
    $normalized = preg_replace($pattern, $replacement, $normalized) ?? $content;

    // Collapse trailing multiple newlines to single newline.
    $normalized = preg_replace("/\n{2,}$/", "\n", $normalized) ?? $content;

    // Convert back to original line ending.
    return $line_ending !== "\n" ? str_replace("\n", $line_ending, $normalized) : $normalized;
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

    $token_end = $token_end ?? $token_begin;

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

    // Preserve original line endings.
    $line_ending = "\n";
    if (str_contains($content, "\r\n")) {
      $line_ending = "\r\n";
    }
    elseif (str_contains($content, "\r")) {
      $line_ending = "\r";
    }

    foreach ($lines as $line) {
      if (str_contains($line, $token_begin)) {
        if ($with_content) {
          $within_token = TRUE;
        }
        continue;
      }
      elseif (str_contains($line, $token_end)) {
        if ($with_content) {
          $within_token = FALSE;
        }
        continue;
      }

      if ($with_content && $within_token) {
        // Skip content as contents of the token.
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
    $token_start = '#;';
    $token_end = '#;';
    $with_content = FALSE;

    if (!is_null($token)) {
      $token_start = '#;< ' . $token;
      $token_end = '#;> ' . $token;
      $with_content = TRUE;
    }

    $files = static::scandirRecursive($directory, static::ignoredPaths());
    foreach ($files as $filename) {
      static::removeTokenInFile($filename, $token_start, $token_end, $with_content);
    }
  }

  /**
   * Get list of paths to ignore.
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
   * Create diff files between baseline and destination directories.
   *
   * @param string $baseline
   *   Baseline directory path.
   * @param string $destination
   *   Destination directory path.
   * @param string $diff
   *   Directory to write diff files to.
   * @param callable|null $before_match_content
   *   Optional callback to process file content before comparison.
   */
  public static function diff(string $baseline, string $destination, string $diff, ?callable $before_match_content = NULL): void {
    static::mkdir($diff);

    $differ = self::compare($baseline, $destination, NULL, $before_match_content)->getDiffer();

    // Early exit: Check if there are any differences at all.
    $absent_left = $differ->getAbsentLeftDiffs();
    $absent_right = $differ->getAbsentRightDiffs();
    $content_diffs = $differ->getContentDiffs();

    if (empty($absent_left) && empty($absent_right) && empty($content_diffs)) {
      return;
    }

    // Process absent left diffs (files in destination but not in baseline).
    if (!empty($absent_left)) {
      foreach (array_keys($absent_left) as $file) {
        $file_dst = $destination . DIRECTORY_SEPARATOR . $file;
        $file_diff = $diff . DIRECTORY_SEPARATOR . $file;
        static::mkdir(dirname($file_diff));
        static::copy($file_dst, $file_diff);
      }
    }

    // Process absent right diffs (files in baseline but not in destination).
    if (!empty($absent_right)) {
      foreach (array_keys($absent_right) as $file) {
        $file_diff = $diff . DIRECTORY_SEPARATOR . $file;
        $parent_dir = dirname($file_diff);
        static::mkdir($parent_dir);
        static::dump($parent_dir . DIRECTORY_SEPARATOR . '-' . basename($file_diff), '');
      }
    }

    // Process content diffs (files that differ in content).
    foreach ($content_diffs as $file => $d) {
      if (!$d instanceof Diff) {
        // @codeCoverageIgnoreStart
        continue;
        // @codeCoverageIgnoreEnd
      }

      $file_diff = $diff . DIRECTORY_SEPARATOR . $file;
      $rendered_content = $d->render();
      if ($rendered_content !== NULL) {
        static::dump($file_diff, $rendered_content);
      }
    }
  }

  /**
   * Synchronize files from source to destination directory.
   *
   * @param string $src
   *   Source directory path.
   * @param string $dst
   *   Destination directory path.
   * @param int $permissions
   *   Permissions to set on created directories.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   */
  public static function sync(string $src, string $dst, int $permissions = 0755, bool $copy_empty_dirs = FALSE): void {
    $src_index = new Index($src);

    $syncer = new Syncer($src_index);

    $syncer->sync($dst, $permissions, $copy_empty_dirs);
  }

  /**
   * Compare files between source and destination directories.
   *
   * @param string $src
   *   Source directory path.
   * @param string $dst
   *   Destination directory path.
   * @param \AlexSkrypnyk\File\Internal\Rules|null $rules
   *   Optional rules for file comparison.
   * @param callable|null $before_match_content
   *   Optional callback to process file content before comparison.
   *
   * @return \AlexSkrypnyk\File\Internal\Comparer
   *   Configured and executed comparer object.
   */
  public static function compare(string $src, string $dst, ?Rules $rules = NULL, ?callable $before_match_content = NULL): Comparer {
    $src_index = new Index($src, $rules, $before_match_content);

    static::mkdir($dst);
    $dst_index = new Index($dst, $rules ?: $src_index->getRules(), $before_match_content);

    $comparer = new Comparer($src_index, $dst_index);

    return $comparer->compare();
  }

  /**
   * Apply patch files to a baseline and produce a destination.
   *
   * @param string $baseline
   *   Baseline directory path.
   * @param string $diff
   *   Directory containing diff/patch files.
   * @param string $destination
   *   Destination directory path where patched files will be written.
   * @param callable|null $before_match_content
   *   Optional callback to process file content before patching.
   */
  public static function patch(string $baseline, string $diff, string $destination, ?callable $before_match_content = NULL): void {
    static::mkdir($destination);

    static::sync($baseline, $destination);

    $patcher = new Patcher($baseline, $destination);

    $diff_files = (new Index($diff))->getFiles();
    foreach ($diff_files as $file) {
      if (str_starts_with($file->getBasename(), '-')) {
        $dst_file = $destination . DIRECTORY_SEPARATOR . $file->getPathFromBasepath() . DIRECTORY_SEPARATOR . substr($file->getBasename(), 1);
        static::remove($dst_file);
      }
      elseif (!Patcher::isPatchFile($file->getPathname())) {
        $dst_file = $destination . DIRECTORY_SEPARATOR . $file->getPathnameFromBasepath();
        static::copy($file->getPathname(), $dst_file);
      }
      else {
        $patcher->addPatchFile($file);
      }
    }

    $patcher->patch();
  }

  /**
   * Add a task to the directory task queue.
   *
   * @param callable $callback
   *   Callback function to execute.
   */
  public static function addTaskDirectory(callable $callback): void {
    static::getTasker()->addTask($callback, 'directory');
  }

  /**
   * Run all tasks for the directory batch.
   *
   * @param string $directory
   *   Directory to scan and process.
   */
  public static function runTaskDirectory(string $directory): void {
    $iterator = function () use ($directory) {
      $files = static::scandirRecursive($directory, static::ignoredPaths());
      foreach ($files as $path) {
        if (File::isExcluded($path)) {
          continue;
        }

        $file = new ExtendedSplFileInfo($path, $directory);
        $original_content = $file->getContent();

        $processed_file = yield $file;

        if ($processed_file instanceof ExtendedSplFileInfo) {
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
  public static function clearTaskDirectory(): void {
    static::getTasker()->clear('directory');
  }

  /**
   * Get the shared Tasker instance.
   *
   * @return \AlexSkrypnyk\File\Internal\Tasker
   *   The shared tasker instance.
   */
  protected static function getTasker(): Tasker {
    static $tasker = NULL;

    if ($tasker === NULL) {
      $tasker = new Tasker();
    }

    return $tasker;
  }

}
