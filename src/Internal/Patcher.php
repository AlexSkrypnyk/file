<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

use AlexSkrypnyk\File\Exception\PatchException;
use AlexSkrypnyk\File\ExtendedSplFileInfo;
use AlexSkrypnyk\File\File;

/**
 * Class Patcher.
 *
 * A limited implementation of the patch command that applies unified diffs
 * to files. Does not handle file removals or renames.
 */
class Patcher {

  /**
   * Array of diff lines, keyed by the source file path.
   *
   * @var array<string, array<int, string>>
   */
  protected $diffs = [];

  /**
   * Array of source files lines, keyed by the source file path.
   *
   * @var array<string, array<int, string>>
   */
  protected $srcLines = [];

  /**
   * Array of destination files lines, keyed by the destination file path.
   *
   * @var array<string, array<int, string>>
   */
  protected $dstLines = [];

  public function __construct(
    protected string $source,
    protected string $destination,
  ) {
  }

  /**
   * Check if a file is a patch file.
   *
   * @param string $filepath
   *   The file path.
   *
   * @return bool
   *   TRUE if the file is a patch file, FALSE otherwise.
   */
  public static function isPatchFile(string $filepath): bool {
    if (!File::exists($filepath) || is_dir($filepath) || is_link($filepath)) {
      return FALSE;
    }
    return File::contains($filepath, '@@');
  }

  /**
   * Add a patch file.
   *
   * @param \AlexSkrypnyk\File\ExtendedSplFileInfo $file
   *   The patch file.
   *
   * @return $this
   */
  public function addPatchFile(ExtendedSplFileInfo $file): static {
    if (!static::isPatchFile($file->getPathname())) {
      throw new PatchException('Invalid patch file', $file->getPathname());
    }

    return $this->addDiff($file->getContent(), $file->getPathnameFromBasepath());
  }

  /**
   * Add a diff.
   *
   * @param string|array $diff
   *   The diff content.
   * @param string $pathname
   *   The source file path.
   *
   * @return $this
   */
  public function addDiff(string|array $diff, string $pathname): static {
    $this->diffs[$pathname] = is_array($diff) ? $diff : static::splitLines($diff);

    return $this;
  }

  /**
   * Apply the patch.
   *
   * @return int
   *   The number of files patched.
   */
  public function patch(): int {
    foreach ($this->diffs as $path => $diff) {
      $src = $this->source . DIRECTORY_SEPARATOR . $path;
      $dst = $this->destination . DIRECTORY_SEPARATOR . $path;

      while ($info = $this->findHunk($diff)) {
        $this->applyHunk($diff, $src, $dst, $info);
      }
    }

    return $this->updateDestinations();
  }

  /**
   * Find a hunk in a diff.
   *
   * @param array $lines
   *   The diff lines.
   *
   * @return array|null
   *   The hunk info or NULL if no hunk is found.
   *   The hunk info is an array with the following keys:
   *   - src_idx: (int) The source line index.
   *   - src_size: (int) The source line size.
   *   - dst_idx: (int) The destination line index.
   *   - dst_size: (int) The destination line size.
   *
   * @throws \AlexSkrypnyk\File\Exception\PatchException
   *   When unexpected EOF is encountered.
   */
  protected function findHunk(array &$lines): ?array {
    $line = current($lines);

    if (!preg_match('/@@ -(\\d+)(,(\\d+))?\\s+\\+(\\d+)(,(\\d+))?\\s+@@($)/A', $line, $m)) {
      return NULL;
    }

    $src_idx = (int) $m[1];
    $src_size = $m[3] !== '' ? (int) $m[3] : 1;

    $dst_idx = (int) $m[4];
    $dst_size = $m[6] !== '' ? (int) $m[6] : 1;

    if (next($lines) === FALSE) {
      $current_key = key($lines);
      $source_file = count($this->diffs) > 0 ? array_key_first($this->diffs) : '';
      throw new PatchException('Unexpected EOF', $source_file, $current_key);
    }

    return [
      'src_idx' => $src_idx,
      'src_size' => $src_size,
      'dst_idx' => $dst_idx,
      'dst_size' => $dst_size,
    ];
  }

  /**
   * Applies a patch hunk to an array of lines.
   *
   * @param array &$lines
   *   Array of file lines to modify.
   * @param string $src
   *   Source file path.
   * @param string $dst
   *   Destination file path.
   * @param array $info
   *   Hunk information containing src_idx, src_size, dst_idx, dst_size.
   */
  protected function applyHunk(array &$lines, string $src, string $dst, array $info): void {
    $src_idx = $info['src_idx'];
    $src_size = $info['src_size'];
    $dst_idx = $info['dst_idx'];
    $dst_size = $info['dst_size'];

    $src_idx--;
    $dst_idx--;

    // Load the source and destination lines if they are not already loaded.
    $this->srcLines[$src] = $this->srcLines[$src] ?? static::splitLines(File::read($src));
    // Use source lines as destination lines if the destination file does not
    // exist.
    $this->dstLines[$dst] = $this->dstLines[$dst] ?? $this->srcLines[$src];

    $src_hunk = [];
    $dst_hunk = [];
    $src_remaining = $src_size;
    $dst_remaining = $dst_size;

    while (($line = current($lines)) !== FALSE) {
      if ($line === '\\ No newline at end of file') {
        next($lines);
        continue;
      }

      $operation = $line[0] ?? '';
      $content = substr($line, 1);

      switch ($operation) {
        case '-':
          if ($src_remaining <= 0) {
            throw new PatchException('Unexpected removal line', $src, key($lines), $line);
          }
          $src_hunk[] = $content;
          $src_remaining--;
          break;

        case '+':
          if ($dst_remaining <= 0) {
            throw new PatchException('Unexpected addition line', $src, key($lines), $line);
          }
          $dst_hunk[] = $content;
          $dst_remaining--;
          break;

        default:
          $src_hunk[] = $content;
          $dst_hunk[] = $content;
          $src_remaining--;
          $dst_remaining--;
          break;
      }

      next($lines);

      if ($src_remaining === 0 && $dst_remaining === 0) {
        break;
      }
    }

    if ($src_remaining !== 0 || $dst_remaining !== 0) {
      throw new PatchException('Hunk mismatch', $src, key($lines));
    }

    // Verify source lines match the expected ones before applying.
    $source_hunk_slice = array_slice($this->srcLines[$src], $src_idx, count($src_hunk));
    if ($source_hunk_slice !== $src_hunk) {
      throw new PatchException('Source file verification failed', $src, key($lines));
    }

    // Replace lines in destination lines with the lines from the hunk.
    array_splice($this->dstLines[$dst], $dst_idx, count($src_hunk), $dst_hunk);
  }

  /**
   * Writes all modified destination files to disk.
   *
   * @return int
   *   Number of files updated.
   */
  protected function updateDestinations(): int {
    foreach ($this->dstLines as $file => $content) {
      $buffer = implode("\n", $content);

      File::dump($file, $buffer);
    }

    return count($this->dstLines);
  }

  /**
   * Splits a string into lines.
   *
   * @param string $content
   *   The content to split.
   *
   * @return array
   *   Array of lines.
   *
   * @throws \Exception
   *   If the content cannot be split.
   */
  protected static function splitLines(string $content): array {
    $lines = preg_split('/(\r\n)|(\r)|(\n)/', $content);

    if ($lines === FALSE) {
      // @codeCoverageIgnoreStart
      throw new PatchException('Failed to split lines.');
      // @codeCoverageIgnoreEnd
    }

    return $lines;
  }

}
