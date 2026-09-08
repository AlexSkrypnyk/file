<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Tests the benchmark comparison used by CI to gate performance changes.
 */
#[CoversNothing]
final class BenchmarkComparisonTest extends TestCase {

  /**
   * Exit code the comparison script uses for a malformed invocation.
   */
  protected const int EXIT_USAGE = 64;

  /**
   * Root of the repository.
   */
  protected string $root;

  /**
   * Directory holding the base and head projects built for a single test.
   */
  protected string $workspace;

  #[\Override]
  protected function setUp(): void {
    $this->root = dirname(__DIR__, 2);
    $this->workspace = $this->root . '/.artifacts/tmp/benchmark-comparison-' . uniqid();
    $this->assertDirectoryDoesNotExist($this->workspace);
    $this->assertTrue(mkdir($this->workspace, 0777, TRUE));
  }

  #[\Override]
  protected function tearDown(): void {
    exec(sprintf('rm -rf %s', escapeshellarg($this->workspace)));
  }

  public function testDetectsRegressionBeyondThreshold(): void {
    $base = $this->createProject('base', 1000);
    $head = $this->createProject('head', 5000);

    [$exit_code, $output] = $this->compare($base, $head, 50);

    $this->assertNotSame(0, $exit_code, 'Comparison must fail on a regression. Output: ' . $output);
    $this->assertNotSame(self::EXIT_USAGE, $exit_code, 'A regression must not be reported as a usage error. Output: ' . $output);
    $this->assertStringContainsString('benchSleep', $output);
  }

  public function testAcceptsUnchangedPerformance(): void {
    $base = $this->createProject('base', 1000);
    $head = $this->createProject('head', 1000);

    [$exit_code, $output] = $this->compare($base, $head, 50);

    $this->assertSame(0, $exit_code, 'Comparison must pass when timings are unchanged. Output: ' . $output);
  }

  public function testAcceptsImprovement(): void {
    $base = $this->createProject('base', 5000);
    $head = $this->createProject('head', 1000);

    [$exit_code, $output] = $this->compare($base, $head, 50);

    $this->assertSame(0, $exit_code, 'Comparison must pass when the head is faster. Output: ' . $output);
  }

  public function testRejectsMissingBaseDirectory(): void {
    $head = $this->createProject('head', 1000);

    [$exit_code, $output] = $this->compare($this->workspace . '/absent', $head, 50);

    $this->assertSame(self::EXIT_USAGE, $exit_code, 'A missing base directory must be reported as a usage error. Output: ' . $output);
  }

  /**
   * Builds a self-contained PHPBench project whose only subject sleeps.
   *
   * @param string $name
   *   Directory name created under the workspace.
   * @param int $sleep
   *   Microseconds each revolution sleeps for.
   *
   * @return string
   *   Absolute path to the created project.
   */
  protected function createProject(string $name, int $sleep): string {
    $dir = $this->workspace . '/' . $name;
    $this->assertTrue(mkdir($dir . '/benchmarks', 0777, TRUE));

    $config = ['runner.path' => 'benchmarks'];
    $this->assertNotFalse(file_put_contents($dir . '/phpbench.json', (string) json_encode($config)));

    $bench = <<<PHP
    <?php

    use PhpBench\\Attributes\\Iterations;
    use PhpBench\\Attributes\\Revs;

    class SleepBench {

      #[Revs(1)]
      #[Iterations(5)]
      public function benchSleep(): void {
        usleep({$sleep});
      }

    }

    PHP;
    $this->assertNotFalse(file_put_contents($dir . '/benchmarks/SleepBench.php', $bench));

    return $dir;
  }

  /**
   * Runs the comparison script over two prepared projects.
   *
   * @return array{0: int, 1: string}
   *   The exit code and the combined output.
   */
  protected function compare(string $base, string $head, int $threshold): array {
    $command = sprintf('%s --base=%s --head=%s --threshold=%d 2>&1', escapeshellarg($this->root . '/.github/scripts/benchmark-compare.sh'), escapeshellarg($base), escapeshellarg($head), $threshold);

    $output = [];
    $exit_code = 0;
    exec($command, $output, $exit_code);

    return [$exit_code, implode(PHP_EOL, $output)];
  }

}
