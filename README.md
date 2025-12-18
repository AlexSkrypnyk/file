<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="logo.png" alt="File logo"/></a>
</p>

<h1 align="center">Utilities to work with files and directories</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/AlexSkrypnyk/file.svg)](https://github.com/AlexSkrypnyk/file/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/AlexSkrypnyk/file.svg)](https://github.com/AlexSkrypnyk/file/pulls)
[![Test PHP](https://github.com/AlexSkrypnyk/file/actions/workflows/test-php.yml/badge.svg)](https://github.com/AlexSkrypnyk/file/actions/workflows/test-php.yml)
[![codecov](https://codecov.io/gh/AlexSkrypnyk/file/graph/badge.svg?token=7WEB1IXBYT)](https://codecov.io/gh/AlexSkrypnyk/file)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/AlexSkrypnyk/file)
![LICENSE](https://img.shields.io/github/license/AlexSkrypnyk/file)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

</div>

---

## Table of Contents

- [Installation](#installation)
- [Usage](#usage)
  - [Available Methods](#available-methods)
  - [Batch Operations](#batch-operations)
  - [Assertion Traits](#assertion-traits)
    - [Directory Assertions Trait](#directory-assertions-trait)
    - [File Assertions Trait](#file-assertions-trait)
- [Maintenance](#maintenance)

## Installation

```bash
composer require alexskrypnyk/file
```

## Usage

This library provides a comprehensive set of utility methods for file and
directory operations, including high-performance batch operations for processing
multiple files efficiently.

All methods are available through the `AlexSkrypnyk\File\File` class.

```php
use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\ContentFile;

try {
  // Get current working directory
  $cwd = File::cwd();

  // Copy a directory recursively
  File::copy('/path/to/source', '/path/to/destination');

  // Check if a file contains a string
  if (File::contains('/path/to/file.txt', 'search term')) {
    // Do something
  }

  // Process string content directly
  $content = File::read('/path/to/file.txt');
  $processed = File::replaceContent($content, 'old', 'new');
  $processed = File::removeToken($processed, '# BEGIN', '# END');
  File::dump('/path/to/file.txt', $processed);

  // Append content to an existing file
  File::append('/path/to/log.txt', "\nNew log entry: " . date('Y-m-d H:i:s'));

  // Or use batch operations for better performance
  File::addDirectoryTask(function(ContentFile $file_info): ContentFile {
    $content = File::replaceContent($file_info->getContent(), 'old', 'new');
    $file_info->setContent($content);
    return $file_info;
  });
  File::runDirectoryTasks('/path/to/directory');

} catch (FileException $exception) {
  // Handle any file operation errors
  echo $exception->getMessage();
}
```

### Available Methods

| Method                           | Description                                                                        |
|----------------------------------|------------------------------------------------------------------------------------|
| `absolute()`                     | Get absolute path for provided absolute or relative file.                          |
| `append()`                       | Append content to an existing file.                                                |
| `collapseEmptyLines()`           | Remove multiple consecutive empty lines from a string.                             |
| `collapseEmptyLinesInFile()`     | Remove multiple consecutive empty lines from a file.                               |
| `collapseEmptyLinesInDir()`      | Remove multiple consecutive empty lines from all files in a directory.             |
| `contains()`                     | Check if file contains a specific string or matches a pattern.                     |
| `copy()`                         | Copy file or directory.                                                            |
| `copyIfExists()`                 | Copy file or directory if it exists.                                               |
| `cwd()`                          | Get current working directory with absolute path.                                  |
| `dir()`                          | Get absolute path for existing directory.                                          |
| `dirIsEmpty()`                   | Check if directory is empty.                                                       |
| `dump()`                         | Write content to a file.                                                           |
| `exists()`                       | Check if file or directory exists.                                                 |
| `findContainingInDir()`          | Find all files in directory containing a specific string.                          |
| `findMatchingPath()`             | Find first path that matches a needle among provided paths.                        |
| `mkdir()`                        | Creates a directory if it doesn't exist.                                           |
| `read()`                         | Read file contents.                                                                |
| `realpath()`                     | Replacement for PHP's `realpath` resolves non-existing paths.                      |
| `remove()`                       | Remove file or directory.                                                          |
| `removeLine()`                   | Remove lines containing a specific string from a string.                           |
| `removeLineInFile()`             | Remove lines containing a specific string from a file.                             |
| `removeLineInDir()`              | Remove lines containing a specific string from all files in a directory.           |
| `removeToken()`                  | Remove tokens and optionally content between tokens from a string.                 |
| `removeTokenInFile()`            | Remove tokens and optionally content between tokens from a file.                   |
| `removeTokenInDir()`             | Remove tokens and optionally content between tokens from all files in a directory. |
| `renameInDir()`                  | Rename files in directory by replacing part of the filename.                       |
| `replaceContent()`               | Replace content in a string.                                                       |
| `replaceContentCallback()`       | Replace content in a string using a callback processor.                            |
| `replaceContentInFile()`         | Replace content in a file.                                                         |
| `replaceContentCallbackInFile()` | Replace content in a file using a callback processor.                              |
| `replaceContentInDir()`          | Replace content in all files in a directory.                                       |
| `replaceContentCallbackInDir()`  | Replace content in all files in a directory using a callback processor.            |
| `rmdir()`                        | Remove directory recursively.                                                      |
| `rmdirIfEmpty()`                 | Remove directory recursively if empty.                                             |
| `scandir()`                      | Recursively scan directory for files.                                              |
| `tmpdir()`                       | Create temporary directory.                                                        |

### Batch Operations

For improved performance when processing multiple files, the library provides
batch operations that minimize directory scans and optimize I/O operations:

| Method                  | Description                                                          |
|-------------------------|----------------------------------------------------------------------|
| `addDirectoryTask()`    | Add a batch task to be executed on all files in a directory.         |
| `runDirectoryTasks()`   | Execute all queued tasks on files in a directory with optimized I/O. |
| `clearDirectoryTasks()` | Clear all queued batch tasks.                                        |

#### Performance Benefits

The batch operations provide significant performance improvements over
traditional file operations:

- **Single directory scan**: Instead of scanning the directory multiple times
- **Single I/O per file**: Each file is read once, processed by all tasks, then
  written once
- **Memory efficient**: Uses generators to handle large file sets without
  loading everything into memory

#### Usage Example

```php
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Internal\ContentFile;

// Traditional approach (slow for multiple operations)
File::replaceContentInDir('/path/to/dir', 'old1', 'new1');
File::replaceContentInDir('/path/to/dir', 'old2', 'new2');
File::removeTokenInDir('/path/to/dir', '# token');

// Callback approach for custom processing
File::replaceContentCallbackInDir('/path/to/dir', function(string $content, string $file_path): string {
  // Custom processing logic with access to file path
  $content = str_replace('old1', 'new1', $content);
  $content = preg_replace('/pattern/', 'replacement', $content);
  // Example: different processing based on file type
  if (str_ends_with($file_path, '.md')) {
    $content = '# ' . $content;
  }
  return strtoupper($content);
});

// Batch approach: significantly faster because while tasks are added first,
// the directory is scanned only once and each file is read/written only once.
File::addDirectoryTask(function(ContentFile $file_info): ContentFile {
  $content = File::replaceContent($file_info->getContent(), 'old1', 'new1');
  $content = File::replaceContent($content, 'old2', 'new2');
  $content = File::removeToken($content, '# token');
  $content = File::collapseEmptyLines($content);
  $file_info->setContent($content);
  return $file_info;
});

// Batch approach with callback processing
File::addDirectoryTask(function(ContentFile $file_info): ContentFile {
  $content = File::replaceContentCallback($file_info->getContent(), function(string $content): string {
    return strtoupper(str_replace('old', 'new', $content));
  });
  $file_info->setContent($content);
  return $file_info;
});

File::runDirectoryTasks('/path/to/dir');
```

**Performance Results**: In tests with 5,000 files across 100 directories
performing 10 operations per file:

- Traditional approach: ~16s (multiple directory scans, multiple I/O per file)
- Batch approach: ~1.7s (**~89% faster**, single directory scan, single I/O per
  file)

#### Architecture

The batch operations are powered by an internal `Tasker` queue management system
that:

- Uses PHP generators for memory-efficient processing of large file sets
- Implements a two-way communication pattern between the queue and file
  processors
- Leverages `ContentFile` objects for file content manipulation
- Provides type-safe object validation to ensure data integrity
- Maintains complete separation between the generic queue system and
  file-specific operations

This architecture allows the library to scale efficiently from small single-file
operations to large-scale batch processing scenarios.

### Assertion Traits

The library includes PHPUnit traits for testing files and directories:

#### Directory Assertions Trait

| Assertion Method                     | Description                                                                               |
|--------------------------------------|-------------------------------------------------------------------------------------------|
| `assertDirectoryContainsString()`    | Assert that a directory contains files with a specific string.                            |
| `assertDirectoryNotContainsString()` | Assert that a directory does not contain files with a specific string.                    |
| `assertDirectoryContainsWord()`      | Assert that a directory contains files with a specific word (bounded by word boundaries). |
| `assertDirectoryNotContainsWord()`   | Assert that a directory does not contain files with a specific word.                      |

Usage example:

```php
use PHPUnit\Framework\TestCase;
use AlexSkrypnyk\File\Testing\DirectoryAssertionsTrait;

class MyTest extends TestCase {
  use DirectoryAssertionsTrait;

  public function testDirectories(): void {
    // Assert directory contains "example" string in at least one file
    $this->assertDirectoryContainsString('/path/to/directory', 'example');

    // Assert directory contains "example" string, ignoring specific files
    $this->assertDirectoryContainsString('/path/to/directory', 'example', ['temp.log', 'cache']);

    // Assert directory does not contain specific word
    $this->assertDirectoryNotContainsWord('/path/to/directory', 'forbidden');
  }
}
```

##### Ignoring Paths in Directory Assertions

The directory assertion methods support ignoring specific paths during searches.
You can ignore paths in two ways:

1. **Per-method ignoring**: Pass an `$ignored` array parameter to individual
   assertion methods
2. **Global ignoring**: Override the `ignoredPaths()` method in your test class

```php
class MyTest extends TestCase {
  use DirectoryAssertionsTrait;

  // Global ignored paths for all directory assertions in this test class
  public static function ignoredPaths(): array {
    return ['.git', 'node_modules', 'vendor', 'temp/cache'];
  }

  public function testWithIgnoredPaths(): void {
    // This will ignore both global ignored paths AND 'logs' directory
    $this->assertDirectoryContainsString('/path/to/dir', 'search_term', ['logs']);

    // Global ignored paths are automatically applied to all directory assertions
    $this->assertDirectoryNotContainsWord('/path/to/dir', 'forbidden');
  }
}
```

**Important Notes:**

- Ignored paths are literal subpaths (not wildcard patterns)
- Global `ignoredPaths()` and per-method `$ignored` parameters are merged
  together
- Both file names and directory paths can be ignored
- Ignored paths are relative to the directory being searched

#### File Assertions Trait

| Assertion Method                  | Description                                                               |
|-----------------------------------|---------------------------------------------------------------------------|
| `assertFileContainsString()`      | Assert that a file contains a specific string.                            |
| `assertFileNotContainsString()`   | Assert that a file does not contain a specific string.                    |
| `assertFileContainsWord()`        | Assert that a file contains a specific word (bounded by word boundaries). |
| `assertFileNotContainsWord()`     | Assert that a file does not contain a specific word.                      |
| `assertFileEqualsFile()`          | Assert that a file equals another file in contents.                       |
| `assertFileNotEqualsFile()`       | Assert that a file does not equal another file in contents.               |
| `assertFilesExist()`              | Assert that multiple files exist in a directory.                          |
| `assertFilesDoNotExist()`         | Assert that multiple files do not exist in a directory.                   |
| `assertFilesWildcardExists()`     | Assert that files matching wildcard pattern(s) exist.                     |
| `assertFilesWildcardDoNotExist()` | Assert that files matching wildcard pattern(s) do not exist.              |

Usage example:

```php
use PHPUnit\Framework\TestCase;
use AlexSkrypnyk\File\Testing\FileAssertionsTrait;

class MyTest extends TestCase {
  use FileAssertionsTrait;

  public function testFiles(): void {
    // Assert file contains "example" string
    $this->assertFileContainsString('/path/to/file.txt', 'example');

    // Assert file contains "test" as a complete word
    $this->assertFileContainsWord('/path/to/file.txt', 'test');

    // Assert file does not contain a partial word
    $this->assertFileNotContainsWord('/path/to/file.txt', 'exampl');

    // Assert two files have identical content
    $this->assertFileEqualsFile('/path/to/expected.txt', '/path/to/actual.txt');

    // Assert two files have different content
    $this->assertFileNotEqualsFile('/path/to/expected.txt', '/path/to/actual.txt');

    // Assert that multiple files exist in a directory
    $this->assertFilesExist('/path/to/directory', ['file1.txt', 'file2.txt']);

    // Assert that multiple files do not exist in a directory
    $this->assertFilesDoNotExist('/path/to/directory', ['file1.txt', 'file2.txt']);

    // Assert that files matching wildcard pattern(s) exist
    $this->assertFilesWildcardExists('*.txt');
    $this->assertFilesWildcardExists(['*.txt', '*.json']);

    // Assert that files matching wildcard pattern(s) do not exist
    $this->assertFilesWildcardDoNotExist('*.log');
    $this->assertFilesWildcardDoNotExist(['*.tmp', '*.cache']);

    // All assertion methods support optional custom failure messages
    $this->assertFileContainsString('/path/to/file.txt', 'example', 'Custom failure message');
    $this->assertFilesExist('/path/to/directory', ['file1.txt'], 'Files should exist');
    $this->assertDirectoryContainsString('/path/to/dir', 'search_term', [], 'Custom message');
  }
}
```

## Maintenance

```bash
composer install
composer lint
composer test

# Run performance benchmarks (PHPBench)
composer benchmark
```

---
_This repository was created using the [Scaffold](https://getscaffold.dev/)
project template_
