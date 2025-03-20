<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\Index;
use AlexSkrypnyk\File\Tests\Traits\LocationsTrait;
use AlexSkrypnyk\File\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus\Error;
use PHPUnit\Framework\TestStatus\Failure;

/**
 * Class UnitTestCase.
 *
 * UnitTestCase fixture class.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
abstract class UnitTestBase extends TestCase {

  use ReflectionTrait;
  use LocationsTrait;

  protected function setUp(): void {
    $cwd = getcwd();
    if ($cwd === FALSE) {
      throw new \RuntimeException('Failed to determine current working directory.');
    }
    self::locationsInit($cwd);
  }

  protected function tearDown(): void {
    // Only update the fixtures for the 'install' tests.
    if (isset(static::$fixtures) && str_contains(static::$fixtures, DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR) && getenv('UPDATE_FIXTURES')) {
      $baseline = File::dir(static::$fixtures . '/../_baseline');
      // Use 'noninteractive' test run as a baseline.
      if (str_contains(static::$fixtures, 'non_interactive')) {
        File::copyIfExists($baseline . DIRECTORY_SEPARATOR . Index::IGNORECONTENT, static::$sut . DIRECTORY_SEPARATOR . Index::IGNORECONTENT);
        File::copyIfExists($baseline . DIRECTORY_SEPARATOR . Index::IGNORECONTENT, static::$tmp . DIRECTORY_SEPARATOR . Index::IGNORECONTENT);
        File::rmdir($baseline);
        File::sync(static::$sut, $baseline);
        File::copyIfExists(static::$tmp . DIRECTORY_SEPARATOR . Index::IGNORECONTENT, $baseline . DIRECTORY_SEPARATOR . Index::IGNORECONTENT);
      }
      File::copyIfExists(static::$fixtures . DIRECTORY_SEPARATOR . Index::IGNORECONTENT, static::$tmp . DIRECTORY_SEPARATOR . Index::IGNORECONTENT);
      File::rmdir(static::$fixtures);
      File::diff($baseline, static::$sut, static::$fixtures);
      File::copyIfExists(static::$tmp . DIRECTORY_SEPARATOR . Index::IGNORECONTENT, static::$fixtures . DIRECTORY_SEPARATOR . Index::IGNORECONTENT);
    }

    // Clean up the directories if the test passed.
    if (!$this->status() instanceof Failure && !$this->status() instanceof Error) {
      self::locationsTearDown();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function onNotSuccessfulTest(\Throwable $t): never {
    // Print the locations information and the exception message.
    fwrite(STDERR, PHP_EOL . 'See below:' . PHP_EOL . PHP_EOL . static::locationsInfo() . PHP_EOL . $t->getMessage() . PHP_EOL);

    parent::onNotSuccessfulTest($t);
  }

  protected function assertDirectoryContainsString(string $needle, string $directory, array $excluded = [], string $message = ''): void {
    $files = File::containsInDir($directory, $needle, $excluded);

    if (empty($files)) {
      $this->fail($message ?: sprintf('Directory should contain "%s", but it does not.', $needle));
    }
  }

  protected function assertDirectoryNotContainsString(string $needle, string $directory, array $excluded = [], string $message = ''): void {
    $files = File::containsInDir($directory, $needle, $excluded);

    if (!empty($files)) {
      $this->fail($message ?: sprintf('Directory should not contain "%s", but it does within files %s.', $needle, implode(', ', $files)));
    }
  }

  protected function assertDirectoryContainsWord(string $needle, string $directory, array $excluded = [], string $message = ''): void {
    $files = File::containsInDir($directory, '/\b' . preg_quote($needle) . '\b/i', $excluded);

    if (empty($files)) {
      $this->fail($message ?: sprintf('Directory should contain "%s" word, but it does not.', $needle));
    }
  }

  protected function assertDirectoryNotContainsWord(string $needle, string $directory, array $excluded = [], string $message = ''): void {
    $files = File::containsInDir($directory, '/\b' . preg_quote($needle) . '\b/i', $excluded);

    if (!empty($files)) {
      $this->fail($message ?: sprintf('Directory should not contain "%s" word, but it does within files %s.', $needle, implode(', ', $files)));
    }
  }

  protected function assertDirectoriesEqual(string $dir1, string $dir2, ?string $message = NULL, ?callable $match_content = NULL, bool $show_diff = TRUE): void {
    $text = File::compare($dir1, $dir2, NULL, $match_content)->render(['show_diff' => $show_diff]);
    if (!empty($text)) {
      $this->fail($message ? $message . PHP_EOL . $text : $text);
    }
    $this->addToAssertionCount(1);
  }

  /**
   * Assert that the system under test is equal to the baseline + diff.
   */
  protected function assertBaselineDiffs(string $baseline, string $diffs, string $actual, ?string $expected = NULL): void {
    if (!is_dir($baseline)) {
      throw new \RuntimeException('The baseline directory does not exist: ' . $baseline);
    }

    // We use the .expected dir to easily assess the combined expected fixture.
    $expected = $expected ?: File::realpath($baseline . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.expected');
    File::rmdir($expected);

    File::patch($baseline, $diffs, $expected);

    // Do not override .ignorecontent file from the baseline directory.
    if (file_exists($baseline . DIRECTORY_SEPARATOR . Index::IGNORECONTENT)) {
      File::copy($baseline . DIRECTORY_SEPARATOR . Index::IGNORECONTENT, $expected . DIRECTORY_SEPARATOR . Index::IGNORECONTENT);
    }

    $this->assertDirectoriesEqual($expected, $actual);
  }

}
