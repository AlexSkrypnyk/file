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
use Symfony\Component\Filesystem\Filesystem;

class File {

  /**
   * Get current working directory with absolute path.
   *
   * @return string
   *   Absolute path to current working directory.
   */
  public static function cwd(): string {
    return static::absolute($_SERVER['PWD'] ?? (string) getcwd());
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
      $path = getcwd() . DIRECTORY_SEPARATOR . $path;
      if ($path[0] === '/') {
        $is_unix_path = FALSE;
      }
    }

    // Resolve path parts (single dot, double dot and double delimiters).
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), static function ($part): bool {
      return strlen($part) > 0;
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
   *   Optional base directory. If not provided, current working directory is used.
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
   * @throws \RuntimeException
   *   When directory does not exist.
   */
  public static function dir(string $directory): string {
    $directory = static::realpath($directory);

    if (!static::exists($directory)) {
      throw new \RuntimeException(sprintf('Directory "%s" does not exist.', $directory));
    }

    if (!is_dir($directory)) {
      throw new \RuntimeException(sprintf('Path "%s" is not a directory.', $directory));
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
   * @throws \RuntimeException
   *   When directory cannot be created or is a file.
   */
  public static function mkdir(string $directory, int $permissions = 0777): string {
    $directory = static::absolute($directory);

    try {
      static::dir($directory);
    }
    catch (\RuntimeException $runtimeException) {
      (new Filesystem())->mkdir($directory, $permissions);
      static::dir($directory);
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
   *   Optional base directory to create temporary directory in. If not provided,
   *   system temporary directory is used.
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
   * @throws \RuntimeException
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
      $path = sprintf('%s%s%s%s', $directory, DIRECTORY_SEPARATOR, $prefix, mt_rand(100000, mt_getrandmax()));
    } while (!static::mkdir($path, $permissions) && $attempts++ < $max_attempts);

    try {
      return static::dir($path);
    }
      // @codeCoverageIgnoreStart
    catch (\RuntimeException $runtimeException) {
      throw new \RuntimeException(sprintf('Unable to create temporary directory "%s".', $path), $runtimeException->getCode(), $runtimeException);
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Find first path that matches a needle among provided paths.
   *
   * @param array|string $paths
   *   Path or array of paths to search in.
   * @param string|null $needle
   *   Optional search needle. If provided, will look for files containing this string.
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
          if (static::contains($file, $needle)) {
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
        $filesystem->copy($source, $dest);
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
      if ($entry == '.' || $entry == '..') {
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
    catch (\RuntimeException $runtimeException) {
      return [];
    }

    $files = scandir($directory);
    if (empty($files)) {
      return [];
    }

    $paths = array_diff($files, ['.', '..']);

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
    if (is_dir($directory) && static::dirIsEmpty($directory)) {
      static::rmdir($directory);
      static::rmdirEmpty(dirname($directory));
    }
  }

  /**
   * Remove file or directory.
   *
   * @param string|iterable $files
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

        (new Filesystem())->rename($filename, $new_filename);

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
      static::replaceContent($filename, $needle, $replacement);
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
  public static function replaceContent(string $file, string $needle, string $replacement): void {
    if (!static::exists($file) || !is_readable($file) || static::isExcluded($file)) {
      return;
    }

    $content = static::read($file);
    if ($content === '' || $content === '0') {
      return;
    }

    if (Strings::isRegex($needle)) {
      $replaced = preg_replace($needle, $replacement, $content);
    }
    else {
      $replaced = str_replace($needle, $replacement, $content);
    }
    if ($replaced != $content) {
      static::dump($file, $replaced);
    }
  }

  /**
   * Remove lines containing a specific string from a file.
   *
   * @param string $file
   *   File path to process.
   * @param string $needle
   *   String to search for in lines.
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

    $lines = array_filter($lines, fn($line): bool => !str_contains($line, $needle));

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
   * @throws \RuntimeException
   *   When begin and end token counts don't match.
   */
  public static function removeToken(string $file, string $token_begin, ?string $token_end = NULL, bool $with_content = FALSE): void {
    if (static::isExcluded($file)) {
      return;
    }

    if (!static::exists($file) || !is_readable($file)) {
      return;
    }

    $token_end = $token_end ?? $token_begin;

    $content = static::read($file);
    if ($content === '' || $content === '0') {
      return;
    }

    if ($token_begin !== $token_end) {
      $token_begin_count = preg_match_all('/' . preg_quote($token_begin) . '/', $content);
      $token_end_count = preg_match_all('/' . preg_quote($token_end) . '/', $content);
      if ($token_begin_count !== $token_end_count) {
        throw new \RuntimeException(sprintf('Invalid begin and end token count in file %s: begin is %s(%s), end is %s(%s).', $file, $token_begin, $token_begin_count, $token_end, $token_end_count));
      }
    }

    $out = [];
    $within_token = FALSE;

    $lines = file($file);
    if (!$lines) {
      return;
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

    self::dump($file, implode('', $out));
  }

  /**
   * Remove tokens and optionally content between tokens from all files in a directory.
   *
   * @param string $directory
   *   Directory to search in.
   * @param string|null $token
   *   Optional token name. If provided, removes content between '#;< token' and '#;> token'.
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
      static::removeToken($filename, $token_start, $token_end, $with_content);
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
      '.+\.bpm',
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

    if (!empty($differ->getAbsentLeftDiffs())) {
      foreach (array_keys($differ->getAbsentLeftDiffs()) as $file) {
        $file_dst = $destination . DIRECTORY_SEPARATOR . $file;
        $file_diff = $diff . DIRECTORY_SEPARATOR . $file;
        static::mkdir(dirname($file_diff));
        static::copy($file_dst, $file_diff);
      }
    }

    if (!empty($differ->getAbsentRightDiffs())) {
      foreach (array_keys($differ->getAbsentRightDiffs()) as $file) {
        $file_diff = $diff . DIRECTORY_SEPARATOR . $file;
        static::mkdir(dirname($file_diff));
        static::dump(dirname($file_diff) . DIRECTORY_SEPARATOR . '-' . basename($file_diff), '');
      }
    }

    foreach ($differ->getContentDiffs() as $file => $d) {
      if (!$d instanceof Diff) {
        continue;
      }

      $file_diff = $diff . DIRECTORY_SEPARATOR . $file;
      static::dump($file_diff, $d->render());
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
}
