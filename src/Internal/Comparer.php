<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

class Comparer implements RenderInterface {

  protected Differ $differ;

  public function __construct(
    protected Index $left,
    protected Index $right,
  ) {
    $this->differ = new Differ();
  }

  public function compare(): static {
    $dir_left_files = $this->left->getFiles();
    $dir_right_files = $this->right->getFiles();

    foreach ($dir_left_files as $left_file) {
      $this->differ->addLeftFile($left_file);
      if (isset($dir_right_files[$left_file->getPathnameFromBasepath()])) {
        $this->differ->addRightFile($dir_right_files[$left_file->getPathnameFromBasepath()]);
      }
    }

    foreach ($dir_right_files as $right_file) {
      $this->differ->addRightFile($right_file);
      if (isset($dir_left_files[$right_file->getPathnameFromBasepath()])) {
        $this->differ->addLeftFile($dir_left_files[$right_file->getPathnameFromBasepath()]);
      }
    }

    return $this;
  }

  public function getDiffer(): Differ {
    return $this->differ;
  }

  public function render(array $options = [], ?callable $renderer = NULL): ?string {
    return call_user_func($renderer ?? [static::class, 'doRender'], $this->left, $this->right, $this->differ, $options);
  }

  protected static function doRender(Index $left, Index $right, Differ $differ, array $options = []): ?string {
    $options += [
      'show_diff' => TRUE,
      // Number of files to include in the diff output. This allows to prevent
      // an output that could potentially eat a lot of memory.
      'show_diff_file_limit' => 10,
    ];

    if (empty($differ->getAbsentLeftDiffs()) && empty($differ->getAbsentRightDiffs()) && empty($differ->getContentDiffs())) {
      return NULL;
    }

    $render = sprintf("Differences between directories \n[left] %s\nand\n[right] %s\n", $left->getDirectory(), $right->getDirectory());

    if (!empty($differ->getAbsentLeftDiffs())) {
      $render .= "Files absent in [left]:\n";
      foreach (array_keys($differ->getAbsentLeftDiffs()) as $file) {
        $render .= sprintf("  %s\n", $file);
      }
    }

    if (!empty($differ->getAbsentRightDiffs())) {
      $render .= "Files absent in [right]:\n";
      foreach (array_keys($differ->getAbsentRightDiffs()) as $file) {
        $render .= sprintf("  %s\n", $file);
      }
    }

    $file_diffs = $differ->getContentDiffs();
    if (!empty($file_diffs)) {
      $render .= "Files that differ in content:\n";

      $file_diffs_render_count = is_int($options['show_diff_file_limit']) ? $options['show_diff_file_limit'] : count($file_diffs);
      foreach ($file_diffs as $file => $diff) {
        if (!$diff instanceof Diff) {
          continue;
        }

        $render .= sprintf("  %s\n", $file);

        if ($options['show_diff'] && $file_diffs_render_count > 0) {
          $render .= '--- DIFF START ---' . PHP_EOL;
          $render .= $diff->render();
          $render .= '--- DIFF END ---' . PHP_EOL;
          $file_diffs_render_count--;
        }
      }
    }

    return $render;
  }

}
