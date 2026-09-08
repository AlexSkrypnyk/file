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
   * Directory holding the checkouts built for a single test.
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
    $base = $this->createCheckout('base', 1000);
    $head = $this->createCheckout('head', 5000);

    [$exit_code, $output] = $this->compare(['--base=' . $base, '--head=' . $head, '--threshold=50']);

    $this->assertNotSame(0, $exit_code, 'A regression must fail the run. Output: ' . $output);
    $this->assertNotSame(self::EXIT_USAGE, $exit_code, 'A regression must not be reported as a usage error. Output: ' . $output);
    $this->assertStringContainsString('benchSleep', $output);
  }

  public function testAcceptsUnchangedPerformance(): void {
    $base = $this->createCheckout('base', 1000);
    $head = $this->createCheckout('head', 1000);

    [$exit_code, $output] = $this->compare(['--base=' . $base, '--head=' . $head, '--threshold=50']);

    $this->assertSame(0, $exit_code, 'Unchanged timings must pass. Output: ' . $output);
  }

  public function testAcceptsImprovement(): void {
    $base = $this->createCheckout('base', 5000);
    $head = $this->createCheckout('head', 1000);

    [$exit_code, $output] = $this->compare(['--base=' . $base, '--head=' . $head, '--threshold=50']);

    $this->assertSame(0, $exit_code, 'A faster head must pass. Output: ' . $output);
  }

  public function testReportsSingleCheckoutWhenBaseOmitted(): void {
    $head = $this->createCheckout('head', 1000);

    [$exit_code, $output] = $this->compare(['--head=' . $head]);

    $this->assertSame(0, $exit_code, 'A single checkout must be reported without a comparison. Output: ' . $output);
    $this->assertStringContainsString('benchSleep', $output);
  }

  public function testWarnsWhenCheckoutPathsDifferInLength(): void {
    $base = $this->createCheckout('base', 1000);
    $head = $this->createCheckout('head-of-a-different-length', 1000);

    [$exit_code, $output] = $this->compare(['--base=' . $base, '--head=' . $head, '--threshold=50']);

    $this->assertSame(0, $exit_code, 'A path length mismatch must warn rather than fail. Output: ' . $output);
    $this->assertStringContainsString('length', $output, 'The run must warn that the two paths differ in length.');
  }

  public function testRejectsMissingBaseDirectory(): void {
    $head = $this->createCheckout('head', 1000);

    [$exit_code, $output] = $this->compare(['--base=' . $this->workspace . '/absent', '--head=' . $head]);

    $this->assertSame(self::EXIT_USAGE, $exit_code, 'A missing base directory must be a usage error. Output: ' . $output);
  }

  /**
   * Builds a self-contained PHPBench checkout whose only subject sleeps.
   *
   * @param string $name
   *   Directory name created under the workspace.
   * @param int $sleep
   *   Microseconds each revolution sleeps for.
   *
   * @return string
   *   Absolute path to the created checkout.
   */
  protected function createCheckout(string $name, int $sleep): string {
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
   * Runs the comparison script.
   *
   * @param array<int, string> $flags
   *   Flags passed to the script.
   *
   * @return array{0: int, 1: string}
   *   The exit code and the combined output.
   */
  protected function compare(array $flags): array {
    $command = sprintf('%s %s 2>&1', escapeshellarg($this->root . '/.github/scripts/benchmark-compare.sh'), implode(' ', array_map(escapeshellarg(...), $flags)));

    $output = [];
    $exit_code = 0;
    exec($command, $output, $exit_code);

    return [$exit_code, implode(PHP_EOL, $output)];
  }

}
