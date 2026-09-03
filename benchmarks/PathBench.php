<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * Benchmarks for path resolution.
 *
 * Every path-taking method in the library funnels through
 * File::realpath(), so its cost is paid once per path on every call.
 *
 * The first group isolates the branches of File::realpath() itself: a
 * path that needs no normalisation, a relative path resolved against the
 * working directory, redundant segments, a deep path, a path under the
 * system temporary directory, a symlink, and a stream wrapper URL.
 *
 * The second group covers the methods that resolve their arguments
 * through it, so a change in resolution cost is visible where callers
 * actually pay it.
 *
 * Subjects that write to the filesystem are excluded. Their deviation on
 * a shared runner swamps the resolution cost being measured, and can
 * exceed the configured retry threshold, which makes phpbench re-run
 * them until the job is killed.
 */
class PathBench {

  use BenchmarkDirectoryTrait;

  /**
   * Number of segments in the deep path.
   */
  protected const DEPTH = 20;

  /**
   * Number of files in the scanned directory structure.
   */
  protected const FILE_COUNT = 10;

  /**
   * Depth of the scanned directory structure.
   */
  protected const STRUCTURE_DEPTH = 2;

  /**
   * Absolute path that needs no normalisation.
   */
  protected const ABSOLUTE_PATH = '/var/www/project/web/sites/default/files/file.txt';

  /**
   * Relative path resolved against the current working directory.
   */
  protected const RELATIVE_PATH = 'web/sites/default/files/file.txt';

  /**
   * Absolute path with single dot, double dot and repeated separators.
   */
  protected const REDUNDANT_PATH = '/var/www/./project/../project/web//sites/default/files/file.txt';

  /**
   * Base directory for the relative path resolution.
   */
  protected const BASE_PATH = '/var/www/project/web';

  /**
   * Absolute path with DEPTH segments.
   */
  protected string $deepPath = '';

  /**
   * Path to an existing symlink.
   */
  protected string $symlinkPath = '';

  /**
   * Path under the system temporary directory.
   */
  protected string $temporaryPath = '';

  /**
   * Stream wrapper URL addressing a directory that does not exist.
   */
  protected string $streamUrl = '';

  /**
   * Path to an existing empty directory.
   */
  protected string $emptyDir = '';

  /**
   * Path to the file the symlink points at.
   */
  protected string $symlinkTarget = '';

  /**
   * Creates the paths used by the benchmarks (not timed).
   */
  public function setUp(): void {
    $this->directoryInitializeTest();

    $segments = [];
    for ($level = 1; $level <= self::DEPTH; $level++) {
      $segments[] = 'level_' . $level;
    }
    $this->deepPath = DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments) . DIRECTORY_SEPARATOR . 'file.txt';

    $this->symlinkTarget = $this->testDir . DIRECTORY_SEPARATOR . 'target.txt';
    file_put_contents($this->symlinkTarget, 'content');

    $this->symlinkPath = $this->testDir . DIRECTORY_SEPARATOR . 'symlink.txt';
    symlink($this->symlinkTarget, $this->symlinkPath);

    $this->temporaryPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'file.txt';
    $this->streamUrl = 'file://' . $this->testDir . '/out/nested';

    $this->emptyDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'empty';
    mkdir($this->emptyDir, 0777, TRUE);
  }

  /**
   * Creates the scanned directory structure (not timed).
   */
  public function setUpStructure(): void {
    $this->setUp();
    $this->directoryCreateStructure($this->testDir, self::FILE_COUNT, [], self::STRUCTURE_DEPTH);
  }

  /**
   * Removes the created paths (not timed).
   */
  public function tearDown(): void {
    $this->directoryCleanup();
  }

  /**
   * Benchmarks an absolute path that needs no normalisation.
   */
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchRealpathAbsolute(): void {
    File::realpath(self::ABSOLUTE_PATH);
  }

  /**
   * Benchmarks a relative path.
   *
   * The path is resolved against the working directory, which costs a
   * second resolution.
   */
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchRealpathRelative(): void {
    File::realpath(self::RELATIVE_PATH);
  }

  /**
   * Benchmarks a path with single dot, double dot and repeated separators.
   */
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchRealpathRedundantSegments(): void {
    File::realpath(self::REDUNDANT_PATH);
  }

  /**
   * Benchmarks a path with DEPTH segments to expose the per-segment cost.
   */
  #[BeforeMethods('setUp')]
  #[AfterMethods('tearDown')]
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchRealpathDeep(): void {
    File::realpath($this->deepPath);
  }

  /**
   * Benchmarks a path under the system temporary directory.
   *
   * Such a path is canonicalised against its own resolved location.
   */
  #[BeforeMethods('setUp')]
  #[AfterMethods('tearDown')]
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchRealpathTemporary(): void {
    File::realpath($this->temporaryPath);
  }

  /**
   * Benchmarks an existing symlink, which is read to its target.
   */
  #[BeforeMethods('setUp')]
  #[AfterMethods('tearDown')]
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchRealpathSymlink(): void {
    File::realpath($this->symlinkPath);
  }

  /**
   * Benchmarks a stream wrapper URL.
   */
  #[BeforeMethods('setUp')]
  #[AfterMethods('tearDown')]
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchRealpathStreamUrl(): void {
    File::realpath($this->streamUrl);
  }

  /**
   * Benchmarks a relative path resolved against an explicit base.
   */
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchAbsolute(): void {
    File::absolute(self::RELATIVE_PATH, self::BASE_PATH);
  }

  /**
   * Benchmarks the working directory lookup.
   */
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchCwd(): void {
    File::cwd();
  }

  /**
   * Benchmarks resolving an existing directory.
   */
  #[BeforeMethods('setUp')]
  #[AfterMethods('tearDown')]
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchDir(): void {
    File::dir($this->testDir);
  }

  /**
   * Benchmarks the path of mkdir() where the directory already exists.
   *
   * Creating a directory per revolution would measure disk throughput
   * rather than resolution, so the existing-directory path is used.
   */
  #[BeforeMethods('setUp')]
  #[AfterMethods('tearDown')]
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchMkdirExisting(): void {
    File::mkdir($this->testDir);
  }

  /**
   * Benchmarks the emptiness check, which resolves and then scans.
   */
  #[BeforeMethods('setUp')]
  #[AfterMethods('tearDown')]
  #[Revs(1000)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchDirIsEmpty(): void {
    File::dirIsEmpty($this->emptyDir);
  }

  /**
   * Benchmarks a recursive scan of FILE_COUNT files.
   */
  #[BeforeMethods('setUpStructure')]
  #[AfterMethods('tearDown')]
  #[Revs(100)]
  #[Warmup(2)]
  #[Iterations(10)]
  public function benchScandir(): void {
    File::scandir($this->testDir);
  }

}
