<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

use AlexSkrypnyk\File\ExtendedSplFileInfo;

/**
 * Manages file differences between source and destination directories.
 */
class Differ {

  /**
   * Collection of file differences.
   *
   * @var \AlexSkrypnyk\File\Internal\Diff[]
   */
  protected array $diffs = [];

  /**
   * Adds a file from the left (source) directory to the diff collection.
   *
   * @param \AlexSkrypnyk\File\ExtendedSplFileInfo $file
   *   The file to add.
   */
  public function addLeftFile(ExtendedSplFileInfo $file): void {
    $this->diffs[$file->getPathnameFromBasepath()] = $this->diffs[$file->getPathnameFromBasepath()] ?? new Diff();
    $this->diffs[$file->getPathnameFromBasepath()]->setLeft($file);
  }

  /**
   * Adds a file from the right (destination) directory to the diff collection.
   *
   * @param \AlexSkrypnyk\File\ExtendedSplFileInfo $file
   *   The file to add.
   */
  public function addRightFile(ExtendedSplFileInfo $file): void {
    $this->diffs[$file->getPathnameFromBasepath()] = $this->diffs[$file->getPathnameFromBasepath()] ?? new Diff();
    $this->diffs[$file->getPathnameFromBasepath()]->setRight($file);
  }

  /**
   * Get an array of absent left diffs.
   *
   * @return Diff[]
   *   An array of diffs that are present in the right directory but not in
   *   the left.
   */
  public function getAbsentLeftDiffs(?callable $cb = NULL): array {
    return $this->filter(function (Diff $diff): bool {
      return !$diff->existsLeft();
    }, $cb);
  }

  /**
   * Get an array of absent right diffs.
   *
   * @return Diff[]
   *   An array of diffs that are present in the left directory but not in
   *   the right.
   */
  public function getAbsentRightDiffs(?callable $cb = NULL): array {
    return $this->filter(function (Diff $diff): bool {
      return !$diff->existsRight();
    }, $cb);
  }

  /**
   * Get an array of content diffs.
   *
   * @return Diff[]|array
   *   An array of diffs that are present in both directories but have different
   *   content.
   */
  public function getContentDiffs(?callable $cb = NULL): array {
    return $this->filter(function (Diff $diff): bool {
      return $diff->existsLeft() && $diff->existsRight() && !$diff->isSameContent();
    }, $cb);
  }

  /**
   * Filters the diffs collection using a callback.
   *
   * @param callable $filter
   *   The filter callback. Should return TRUE to include an item.
   * @param callable|null $cb
   *   Optional transformation callback applied to each filtered diff.
   *
   * @return array
   *   Filtered (and optionally transformed) array of diffs.
   */
  protected function filter(callable $filter, ?callable $cb = NULL): array {
    $diffs = array_filter($this->diffs, $filter);

    if (is_callable($cb)) {
      foreach ($diffs as $path => $diff) {
        $diffs[$path] = $cb($diff);
      }
    }

    return $diffs;
  }

}
