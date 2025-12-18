<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Internal\ContentFile;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

#[CoversClass(File::class)]
#[CoversMethod(File::class, 'addDirectoryTask')]
#[CoversMethod(File::class, 'runDirectoryTasks')]
#[CoversMethod(File::class, 'clearDirectoryTasks')]
#[CoversMethod(File::class, 'getTasker')]
class FileTaskTest extends UnitTestCase {

  protected string $testTmpDir;

  #[\Override]
  protected function setUp(): void {
    $this->testTmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('file_task_test_', TRUE);
    mkdir($this->testTmpDir, 0777, TRUE);
  }

  #[\Override]
  protected function tearDown(): void {
    if (is_dir($this->testTmpDir)) {
      File::rmdir($this->testTmpDir);
    }
    File::clearDirectoryTasks();
  }

  public function testAddTaskDirectory(): void {
    $executed_files = [];

    $callback = function (ContentFile $file_info) use (&$executed_files): ContentFile {
      $executed_files[] = $file_info->getBasename();
      return $file_info;
    };

    File::addDirectoryTask($callback);

    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'task_test';
    mkdir($test_dir, 0777);
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'test.txt', 'content');

    File::runDirectoryTasks($test_dir);

    $this->assertContains('test.txt', $executed_files, 'Task should be executed for the file');
  }

  public function testRunTaskDirectoryWithMultipleTasks(): void {
    $execution_log = [];

    File::addDirectoryTask(function (ContentFile $file_info) use (&$execution_log): ContentFile {
      $execution_log[] = 'task1:' . $file_info->getBasename();
      return $file_info;
    });

    File::addDirectoryTask(function (ContentFile $file_info) use (&$execution_log): ContentFile {
      $execution_log[] = 'task2:' . $file_info->getBasename();
      return $file_info;
    });

    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'multi_task_test';
    mkdir($test_dir, 0777);
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'file1.txt', 'content1');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'file2.txt', 'content2');

    File::runDirectoryTasks($test_dir);

    $expected = ['task1:file1.txt', 'task2:file1.txt', 'task1:file2.txt', 'task2:file2.txt'];
    $this->assertSame($expected, $execution_log, 'All tasks should execute in order for each file');
  }

  public function testRunTaskDirectoryWithFileOperations(): void {
    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'file_ops_test';
    mkdir($test_dir, 0777);
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'replace_test.txt', 'old_text content');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'token_test.txt', "line1\n#; remove\nline3");

    File::addDirectoryTask(function (ContentFile $file_info): ContentFile {
      $processed_content = File::replaceContent($file_info->getContent(), 'old_text', 'new_text');
      $file_info->setContent($processed_content);
      return $file_info;
    });

    File::addDirectoryTask(function (ContentFile $file_info): ContentFile {
      $processed_content = File::removeToken($file_info->getContent(), '#;');
      $file_info->setContent($processed_content);
      return $file_info;
    });

    File::runDirectoryTasks($test_dir);

    $replace_content = file_get_contents($test_dir . DIRECTORY_SEPARATOR . 'replace_test.txt');
    $this->assertIsString($replace_content, 'File content should be readable');
    $this->assertStringContainsString('new_text', $replace_content, 'Content replacement should work');
    $this->assertStringNotContainsString('old_text', $replace_content, 'Old content should be replaced');

    $token_content = file_get_contents($test_dir . DIRECTORY_SEPARATOR . 'token_test.txt');
    $this->assertIsString($token_content, 'File content should be readable');
    $this->assertStringNotContainsString('#;', $token_content, 'Tokens should be removed');
    $this->assertStringContainsString('line1', $token_content, 'Non-token content should remain');
    $this->assertStringContainsString('line3', $token_content, 'Non-token content should remain');
  }

  public function testClearTaskDirectory(): void {
    $executed = FALSE;

    File::addDirectoryTask(function (ContentFile $file_info) use (&$executed): ContentFile {
      $executed = TRUE;
      return $file_info;
    });

    File::clearDirectoryTasks();

    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'clear_test';
    mkdir($test_dir, 0777);
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'test.txt', 'content');

    File::runDirectoryTasks($test_dir);

    $this->assertFalse($executed, 'Cleared tasks should not execute');
  }

  public function testRunTaskDirectoryWithEmptyDirectory(): void {
    $executed = FALSE;

    File::addDirectoryTask(function (ContentFile $file_info) use (&$executed): ContentFile {
      $executed = TRUE;
      return $file_info;
    });

    $empty_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'empty_test';
    mkdir($empty_dir, 0777);

    File::runDirectoryTasks($empty_dir);

    $this->assertFalse($executed, 'Tasks should not execute when directory is empty');
  }

  public function testTaskDirectoryIgnoresDefaultPaths(): void {
    $processed_files = [];

    File::addDirectoryTask(function (ContentFile $file_info) use (&$processed_files): ContentFile {
      $processed_files[] = $file_info->getBasename();
      return $file_info;
    });

    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'ignore_test';
    mkdir($test_dir, 0777);

    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'normal.txt', 'content');

    mkdir($test_dir . DIRECTORY_SEPARATOR . '.git', 0777);
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'config', 'git config');

    File::runDirectoryTasks($test_dir);

    $this->assertContains('normal.txt', $processed_files, 'Normal files should be processed');
    $this->assertNotContains('config', $processed_files, 'Files in ignored directories should not be processed');
  }

  public function testTaskDirectoryBatchExecution(): void {
    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'batch_test';
    mkdir($test_dir, 0777);

    for ($i = 1; $i <= 5; $i++) {
      file_put_contents($test_dir . DIRECTORY_SEPARATOR . sprintf('file%d.txt', $i), sprintf('content %d with old_value', $i));
    }

    File::addDirectoryTask(function (ContentFile $file_info): ContentFile {
      $processed_content = File::replaceContent($file_info->getContent(), 'old_value', 'new_value');
      $file_info->setContent($processed_content);
      return $file_info;
    });

    File::addDirectoryTask(function (ContentFile $file_info): ContentFile {
      $processed_content = $file_info->getContent() . "\n-- processed --";
      $file_info->setContent($processed_content);
      return $file_info;
    });

    File::runDirectoryTasks($test_dir);

    for ($i = 1; $i <= 5; $i++) {
      $content = file_get_contents($test_dir . DIRECTORY_SEPARATOR . sprintf('file%d.txt', $i));
      $this->assertIsString($content, 'File content should be readable');
      $this->assertStringContainsString('new_value', $content, sprintf('File %d should have replaced content', $i));
      $this->assertStringContainsString('-- processed --', $content, sprintf('File %d should have processing marker', $i));
      $this->assertStringNotContainsString('old_value', $content, sprintf('File %d should not have old content', $i));
    }
  }

  public function testRunTaskDirectoryOnlyWritesChangedFiles(): void {
    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'write_optimization_test';
    mkdir($test_dir, 0777);

    // Create test files with different scenarios.
    $unchanged_file = $test_dir . DIRECTORY_SEPARATOR . 'unchanged.txt';
    $changed_file = $test_dir . DIRECTORY_SEPARATOR . 'changed.txt';
    $new_content_file = $test_dir . DIRECTORY_SEPARATOR . 'new_content.txt';

    file_put_contents($unchanged_file, 'keep this content');
    file_put_contents($changed_file, 'old content to replace');
    file_put_contents($new_content_file, 'original content');

    // Get original modification times.
    clearstatcache();
    $unchanged_mtime_before = filemtime($unchanged_file);
    $changed_mtime_before = filemtime($changed_file);
    $new_content_mtime_before = filemtime($new_content_file);

    // Sleep to ensure mtime difference if files are touched.
    sleep(1);

    // Add tasks that modify some files but not others.
    File::addDirectoryTask(function (ContentFile $file_info): ContentFile {
      $filename = $file_info->getBasename();
      $content = $file_info->getContent();

      if ($filename === 'unchanged.txt') {
        // Task that doesn't actually change content.
        // No change.
        $processed_content = $content;
      }
      elseif ($filename === 'changed.txt') {
        // Task that changes content.
        $processed_content = File::replaceContent($content, 'old content', 'new content');
      }
      elseif ($filename === 'new_content.txt') {
        // Task that sets completely new content.
        $processed_content = 'completely new content';
      }
      else {
        $processed_content = $content;
      }

      $file_info->setContent($processed_content);
      return $file_info;
    });

    File::runDirectoryTasks($test_dir);

    // Clear file stat cache and check modification times.
    clearstatcache();
    $unchanged_mtime_after = filemtime($unchanged_file);
    $changed_mtime_after = filemtime($changed_file);
    $new_content_mtime_after = filemtime($new_content_file);

    // Verify content changes.
    $this->assertSame('keep this content', file_get_contents($unchanged_file), 'Unchanged file content should remain the same');
    $this->assertSame('new content to replace', file_get_contents($changed_file), 'Changed file should have new content');
    $this->assertSame('completely new content', file_get_contents($new_content_file), 'New content file should have new content');

    // Verify modification times - unchanged file should have same mtime.
    $this->assertSame($unchanged_mtime_before, $unchanged_mtime_after, 'Unchanged file modification time should not change');

    // Changed files should have updated modification times.
    $this->assertGreaterThan($changed_mtime_before, $changed_mtime_after, 'Changed file modification time should be updated');
    $this->assertGreaterThan($new_content_mtime_before, $new_content_mtime_after, 'New content file modification time should be updated');
  }

  public function testRunTaskDirectoryNoOpTasksPreserveFiles(): void {
    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'noop_test';
    mkdir($test_dir, 0777);

    // Create multiple test files.
    for ($i = 1; $i <= 3; $i++) {
      file_put_contents($test_dir . DIRECTORY_SEPARATOR . sprintf('file%d.txt', $i), 'original content ' . $i);
    }

    // Get original modification times.
    clearstatcache();
    $original_mtimes = [];
    for ($i = 1; $i <= 3; $i++) {
      $original_mtimes[$i] = filemtime($test_dir . DIRECTORY_SEPARATOR . sprintf('file%d.txt', $i));
    }

    // Sleep to ensure mtime difference if files are touched.
    sleep(1);

    // Add a task that doesn't modify content at all.
    File::addDirectoryTask(function (ContentFile $file_info): ContentFile {
      // No-op task - just return the file as-is.
      return $file_info;
    });

    File::runDirectoryTasks($test_dir);

    // Clear file stat cache and check modification times.
    clearstatcache();
    for ($i = 1; $i <= 3; $i++) {
      $file_path = $test_dir . DIRECTORY_SEPARATOR . sprintf('file%d.txt', $i);
      $current_mtime = filemtime($file_path);

      // Verify content unchanged.
      $this->assertSame('original content ' . $i, file_get_contents($file_path), sprintf('File %d content should be unchanged', $i));

      // Verify modification time unchanged.
      $this->assertSame($original_mtimes[$i], $current_mtime, sprintf('File %d modification time should not change for no-op task', $i));
    }
  }

  public function testRunTaskDirectoryEmptyStringVsContentChanges(): void {
    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'empty_string_test';
    mkdir($test_dir, 0777);

    // Test various edge cases for content comparison.
    $test_cases = [
      'empty_to_content.txt' => ['', 'new content'],
      'content_to_empty.txt' => ['original content', ''],
      'zero_to_content.txt' => ['0', 'new content'],
      'content_to_zero.txt' => ['original', '0'],
    // Should not change.
      'null_byte.txt' => ["content\0with\0nulls", "content\0with\0nulls"],
    // Should not change.
      'whitespace.txt' => ['  spaces  ', '  spaces  '],
    // Should not change.
      'newlines.txt' => ["line1\nline2\n", "line1\nline2\n"],
    ];

    // Create test files.
    foreach ($test_cases as $filename => [$original]) {
      file_put_contents($test_dir . DIRECTORY_SEPARATOR . $filename, $original);
    }

    // Get original modification times.
    clearstatcache();
    $original_mtimes = [];
    foreach (array_keys($test_cases) as $filename) {
      $original_mtimes[$filename] = filemtime($test_dir . DIRECTORY_SEPARATOR . $filename);
    }

    sleep(1);

    // Add task that sets specific content for each file.
    File::addDirectoryTask(function (ContentFile $file_info) use ($test_cases): ContentFile {
      $filename = $file_info->getBasename();

      if (isset($test_cases[$filename])) {
        $new_content = $test_cases[$filename][1];
        $file_info->setContent($new_content);
      }

      return $file_info;
    });

    File::runDirectoryTasks($test_dir);

    // Verify results.
    clearstatcache();
    foreach ($test_cases as $filename => [$original, $expected]) {
      $file_path = $test_dir . DIRECTORY_SEPARATOR . $filename;
      $actual_content = file_get_contents($file_path);
      $current_mtime = filemtime($file_path);

      // Verify content is as expected.
      $this->assertSame($expected, $actual_content, sprintf('File %s should have expected content', $filename));

      // Verify modification time behavior.
      if ($original === $expected) {
        // Content didn't change - mtime should be preserved.
        $this->assertSame($original_mtimes[$filename], $current_mtime, sprintf('File %s mtime should be preserved when content unchanged', $filename));
      }
      else {
        // Content changed - mtime should be updated.
        $this->assertGreaterThan($original_mtimes[$filename], $current_mtime, sprintf('File %s mtime should be updated when content changed', $filename));
      }
    }
  }

  public function testRunTaskDirectoryRespectsFileExclusions(): void {
    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'exclusion_test';
    mkdir($test_dir, 0777);

    // Create test files - regular text files and image files.
    $regular_file = $test_dir . DIRECTORY_SEPARATOR . 'regular.txt';
    $image_file = $test_dir . DIRECTORY_SEPARATOR . 'image.jpg';
    $png_file = $test_dir . DIRECTORY_SEPARATOR . 'photo.png';
    $doc_file = $test_dir . DIRECTORY_SEPARATOR . 'document.md';

    file_put_contents($regular_file, 'regular content');
    file_put_contents($image_file, 'fake image content');
    file_put_contents($png_file, 'fake png content');
    file_put_contents($doc_file, 'document content');

    // Track which files were processed.
    $processed_files = [];

    // Add task that tracks which files are processed.
    File::addDirectoryTask(function (ContentFile $file_info) use (&$processed_files): ContentFile {
      $processed_files[] = $file_info->getBasename();
      $content = $file_info->getContent() . ' - processed';
      $file_info->setContent($content);
      return $file_info;
    });

    File::runDirectoryTasks($test_dir);

    // Verify only non-excluded files were processed
    // Text files should be processed.
    $this->assertContains('regular.txt', $processed_files, 'Regular text file should be processed');
    $this->assertContains('document.md', $processed_files, 'Markdown file should be processed');

    // Image files should be excluded by isExcluded() method.
    $this->assertNotContains('image.jpg', $processed_files, 'JPG image file should be excluded from processing');
    $this->assertNotContains('photo.png', $processed_files, 'PNG image file should be excluded from processing');

    // Verify file content changes only happened for processed files.
    $this->assertStringContainsString('- processed', file_get_contents($regular_file) ?: '', 'Regular file should be processed');
    $this->assertStringContainsString('- processed', file_get_contents($doc_file) ?: '', 'Document file should be processed');
    $this->assertStringNotContainsString('- processed', file_get_contents($image_file) ?: '', 'Image file should not be processed');
    $this->assertStringNotContainsString('- processed', file_get_contents($png_file) ?: '', 'PNG file should not be processed');
  }

  public function testRunTaskDirectoryExcludesIgnoredDirectories(): void {
    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'ignore_dirs_test';
    mkdir($test_dir, 0777);

    // Create regular files that should be processed.
    $regular_file = $test_dir . DIRECTORY_SEPARATOR . 'regular.txt';
    file_put_contents($regular_file, 'regular content');

    // Create .git directory and file (should be excluded by ignoredPaths)
    $git_dir = $test_dir . DIRECTORY_SEPARATOR . '.git';
    mkdir($git_dir, 0777);
    $git_file = $git_dir . DIRECTORY_SEPARATOR . 'config';
    file_put_contents($git_file, 'git config content');

    // Create vendor directory and file (should be excluded by ignoredPaths)
    $vendor_dir = $test_dir . DIRECTORY_SEPARATOR . 'vendor';
    mkdir($vendor_dir, 0777);
    $vendor_file = $vendor_dir . DIRECTORY_SEPARATOR . 'package.php';
    file_put_contents($vendor_file, 'vendor package content');

    // Create node_modules directory and file
    // (should be excluded by ignoredPaths).
    $node_modules_dir = $test_dir . DIRECTORY_SEPARATOR . 'node_modules';
    mkdir($node_modules_dir, 0777);
    $node_modules_file = $node_modules_dir . DIRECTORY_SEPARATOR . 'module.js';
    file_put_contents($node_modules_file, 'module content');

    // Track processed files.
    $processed_files = [];

    File::addDirectoryTask(function (ContentFile $file_info) use (&$processed_files): ContentFile {
      $processed_files[] = $file_info->getPathname();
      return $file_info;
    });

    File::runDirectoryTasks($test_dir);

    // Only the regular file should be processed
    // Files in ignored directories (.git, vendor, node_modules)
    // should not appear.
    $this->assertCount(1, $processed_files, 'Expected exactly 1 processed file');

    // Use realpath to resolve symlinks for comparison
    // (macOS /var -> /private/var).
    $expected_realpath = realpath($regular_file);
    $processed_realpaths = array_map('realpath', $processed_files);
    $this->assertContains($expected_realpath, $processed_realpaths, 'Regular file should be processed');

    // Verify excluded directory files are not processed.
    $git_realpath = realpath($git_file);
    $vendor_realpath = realpath($vendor_file);
    $node_modules_realpath = realpath($node_modules_file);

    $this->assertNotContains($git_realpath, $processed_realpaths, 'Git config file should be excluded');
    $this->assertNotContains($vendor_realpath, $processed_realpaths, 'Vendor file should be excluded');
    $this->assertNotContains($node_modules_realpath, $processed_realpaths, 'Node modules file should be excluded');
  }

  public function testRunTaskDirectorySkipsExcludedImageFiles(): void {
    $test_dir = $this->testTmpDir . DIRECTORY_SEPARATOR . 'skip_images_test';
    mkdir($test_dir, 0777);

    // Create a mix of files - some should be excluded by isExcluded()
    $text_file = $test_dir . DIRECTORY_SEPARATOR . 'document.txt';
    $jpg_file = $test_dir . DIRECTORY_SEPARATOR . 'photo.jpg';
    $png_file = $test_dir . DIRECTORY_SEPARATOR . 'image.png';
    $log_file = $test_dir . DIRECTORY_SEPARATOR . 'debug.log';
    $js_file = $test_dir . DIRECTORY_SEPARATOR . 'script.js';

    file_put_contents($text_file, 'text content');
    file_put_contents($jpg_file, 'jpg content');
    file_put_contents($png_file, 'png content');
    file_put_contents($log_file, 'log content');
    file_put_contents($js_file, 'js content');

    sleep(1);

    // Count how many times the task function is called.
    $task_call_count = 0;
    $processed_filenames = [];

    File::addDirectoryTask(function (ContentFile $file_info) use (&$task_call_count, &$processed_filenames): ContentFile {
      $task_call_count++;
      $processed_filenames[] = $file_info->getBasename();

      // Modify content so we can detect if file was processed.
      $content = $file_info->getContent() . ' - modified';
      $file_info->setContent($content);
      return $file_info;
    });

    File::runDirectoryTasks($test_dir);

    // Verify image files are excluded but text files are processed.
    $this->assertContains('document.txt', $processed_filenames, 'Text file should be processed');
    $this->assertContains('debug.log', $processed_filenames, 'Log file should be processed');
    $this->assertContains('script.js', $processed_filenames, 'JS file should be processed');

    // Image files should be excluded.
    $this->assertNotContains('photo.jpg', $processed_filenames, 'JPG file should be excluded');
    $this->assertNotContains('image.png', $processed_filenames, 'PNG file should be excluded');

    // Verify task was called only for non-image files.
    $this->assertSame(3, $task_call_count, 'Task should be called 3 times (for txt, log, js files)');

    // Verify excluded files were not modified.
    $this->assertStringContainsString(' - modified', file_get_contents($text_file) ?: '', 'Text file should be modified');
    $this->assertStringContainsString(' - modified', file_get_contents($log_file) ?: '', 'Log file should be modified');
    $this->assertStringContainsString(' - modified', file_get_contents($js_file) ?: '', 'JS file should be modified');

    $this->assertStringNotContainsString(' - modified', file_get_contents($jpg_file) ?: '', 'JPG file should not be modified');
    $this->assertStringNotContainsString(' - modified', file_get_contents($png_file) ?: '', 'PNG file should not be modified');
  }

}
