<p align="center">
  <a href="" rel="noopener">
  <img height=100px src="logo.png" alt="File logo"/></a>
</p>

<h1 align="center">Utilities to work with files and directories</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/AlexSkrypnyk/file.svg)](https://github.com/AlexSkrypnyk/file/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/AlexSkrypnyk/file.svg)](https://github.com/AlexSkrypnyk/file/pulls)
[![Test PHP](https://github.com/AlexSkrypnyk/file/actions/workflows/test-php.yml/badge.svg)](https://github.com/AlexSkrypnyk/file/actions/workflows/test-php.yml)
[![codecov](https://codecov.io/gh/AlexSkrypnyk/file/graph/badge.svg?token=Ln629nGv67)](https://codecov.io/gh/AlexSkrypnyk/file)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/AlexSkrypnyk/file)
![LICENSE](https://img.shields.io/github/license/AlexSkrypnyk/file)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

</div>

---

## Features

- 📁 Path, file and directory utilities: resolve, copy, scan, rename and remove.
- ✏️ Content operations on a string, a single file, or every file in a directory: replace, remove lines, remove tokens, collapse empty lines.
- ⚡ Batch processing that scans a directory once and reads and writes each file once, however many operations are queued.
- 🔍 Pattern-based replacement with presets for versions, hashes, Docker tags and GitHub Actions references, plus per-rule exclusions.
- ✅ PHPUnit assertion traits for asserting on file and directory state.
- ↩️ Line-based operations preserve line endings: the dominant ending in the input is detected and restored on output.

## Table of Contents

- [Installation](#installation)
- [Usage](#usage)
  - [Available Methods](#available-methods)
  - [Batch Operations](#batch-operations)
  - [Content Replacement](#content-replacement)
  - [Assertion Traits](#assertion-traits)
    - [Directory Assertions Trait](#directory-assertions-trait)
    - [File Assertions Trait](#file-assertions-trait)
- [Contributing](#contributing)
- [Updating](#updating)

## Installation

Requires PHP 8.2 or newer.

```bash
composer require alexskrypnyk/file
```

## Usage

This library provides a comprehensive set of utility methods for file and directory operations, including high-performance batch operations for processing multiple files efficiently.

All methods are available through the `AlexSkrypnyk\File\File` class. File operation errors are reported by throwing a `FileException`.

```php
use AlexSkrypnyk\File\ContentFile\ContentFile;
use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\File;

try {
  // Get current working directory.
  $cwd = File::cwd();

  // Copy a directory recursively.
  File::copy('/path/to/source', '/path/to/destination');

  // Check if a file contains a string.
  if (File::contains('/path/to/file.txt', 'search term')) {
    // Do something.
  }

  // Process string content directly.
  $content = File::read('/path/to/file.txt');
  $processed = File::replaceContent($content, 'old', 'new');
  $processed = File::removeToken($processed, '# BEGIN', '# END');
  File::dump('/path/to/file.txt', $processed);

  // Append content to an existing file.
  File::append('/path/to/log.txt', "\nEntry: " . date('Y-m-d H:i:s'));

  // Or use batch operations for better performance.
  File::addDirectoryTask(function (ContentFile $file): ContentFile {
    $content = File::replaceContent($file->getContent(), 'old', 'new');
    $file->setContent($content);
    return $file;
  });
  File::runDirectoryTasks('/path/to/directory');
}
catch (FileException $exception) {
  // Handle any file operation errors.
  echo $exception->getMessage();
}
```

### Available Methods

| Method                       | Description                                                                        |
|------------------------------|------------------------------------------------------------------------------------|
| `absolute()`                 | Get absolute path for provided absolute or relative file.                          |
| `append()`                   | Append content to an existing file.                                                |
| `collapseEmptyLines()`       | Remove multiple consecutive empty lines from a string.                             |
| `collapseEmptyLinesInFile()` | Remove multiple consecutive empty lines from a file.                               |
| `collapseEmptyLinesInDir()`  | Remove multiple consecutive empty lines from all files in a directory.             |
| `contains()`                 | Check if file contains a specific string or matches a pattern.                     |
| `copy()`                     | Copy file or directory.                                                            |
| `copyIfExists()`             | Copy file or directory if it exists.                                               |
| `cwd()`                      | Get current working directory with absolute path.                                  |
| `dir()`                      | Get absolute path for existing directory.                                          |
| `dirIsEmpty()`               | Check if directory is empty.                                                       |
| `dump()`                     | Write content to a file.                                                           |
| `exists()`                   | Check if file or directory exists.                                                 |
| `findContainingInDir()`      | Find all files in directory containing a specific string.                          |
| `findMatchingPath()`         | Find first path that matches a needle among provided paths.                        |
| `ignoredPaths()`             | Get the default list of ignored subpaths, optionally merged with extra ones.        |
| `mkdir()`                    | Creates a directory if it doesn't exist.                                           |
| `read()`                     | Read file contents.                                                                |
| `realpath()`                 | Replacement for PHP's `realpath` resolves non-existing paths.                      |
| `remove()`                   | Remove file or directory.                                                          |
| `removeLine()`               | Remove lines containing a specific string or pattern from a string.                |
| `removeLineInFile()`         | Remove lines containing a specific string or pattern from a file.                  |
| `removeLineInDir()`          | Remove lines containing a specific string or pattern from all files in a directory. |
| `removeToken()`              | Remove tokens and optionally content between tokens from a string.                 |
| `removeTokenInFile()`        | Remove tokens and optionally content between tokens from a file.                   |
| `removeTokenInDir()`         | Remove tokens and optionally content between tokens from all files in a directory. |
| `renameInDir()`              | Rename files in directory by replacing part of the filename.                       |
| `replaceContent()`           | Replace content in a string.                                                       |
| `replaceContentInFile()`     | Replace content in a file.                                                         |
| `replaceContentInDir()`      | Replace content in all files in a directory.                                       |
| `rmdir()`                    | Remove directory recursively.                                                      |
| `rmdirIfEmpty()`             | Remove directory recursively if empty.                                             |
| `scandir()`                  | Recursively scan directory for files.                                              |
| `tmpdir()`                   | Create temporary directory.                                                        |

Methods that accept a needle also accept a regular expression. A needle is treated as a pattern when it starts with one of the `/`, `#`, `~`, `@` or `%` delimiters and is a valid expression, so `'/\.php$/'` matches by pattern while `'text'` matches literally.

The directory-wide methods skip the paths returned by `ignoredPaths()` - `/.git/`, `/.idea/`, `/vendor/` and `/node_modules/`. The methods that rewrite the content of a single file additionally skip images: `.png`, `.jpg`, `.jpeg`, `.bmp` and `.tiff`.

### Batch Operations

For improved performance when processing multiple files, the library provides batch operations that minimize directory scans and optimize I/O operations:

| Method                  | Description                                                          |
|-------------------------|----------------------------------------------------------------------|
| `addDirectoryTask()`    | Add a batch task to be executed on all files in a directory.         |
| `runDirectoryTasks()`   | Execute all queued tasks on files in a directory with optimized I/O. |
| `clearDirectoryTasks()` | Clear all queued batch tasks.                                        |

Running the queue also clears it, so each `runDirectoryTasks()` call processes only the tasks added since the previous run.

#### Performance Benefits

The batch operations provide significant performance improvements over traditional file operations:

- **Single directory scan**: Instead of scanning the directory multiple times
- **Single I/O per file**: Each file is read once, processed by all tasks, then written once
- **Memory efficient**: Uses generators to handle large file sets without loading everything into memory

#### Usage Example

```php
use AlexSkrypnyk\File\ContentFile\ContentFile;
use AlexSkrypnyk\File\File;

// Traditional approach (slow for multiple operations).
File::replaceContentInDir('/path/to/dir', 'old1', 'new1');
File::replaceContentInDir('/path/to/dir', 'old2', 'new2');
File::removeTokenInDir('/path/to/dir', '# token');

// Batch approach: significantly faster because while tasks are added
// first, the directory is scanned only once and each file is read and
// written only once.
File::addDirectoryTask(function (ContentFile $file): ContentFile {
  $content = File::replaceContent($file->getContent(), 'old1', 'new1');
  $content = File::replaceContent($content, 'old2', 'new2');
  $content = File::removeToken($content, '# token');
  $content = File::collapseEmptyLines($content);
  $file->setContent($content);
  return $file;
});

File::runDirectoryTasks('/path/to/dir');
```

A task can also drive a `Replacer` for more complex replacements. Note that `Replacer::replace()` takes its content by reference and rewrites the variable in place:

```php
use AlexSkrypnyk\File\ContentFile\ContentFile;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Replacer\Replacement;
use AlexSkrypnyk\File\Replacer\Replacer;

File::addDirectoryTask(function (ContentFile $file): ContentFile {
  $content = $file->getContent();

  Replacer::create()
    ->addReplacement(
      Replacement::create('version', '/v\d+\.\d+\.\d+/', '__VERSION__')
    )
    ->addReplacement(
      Replacement::create('year', '/20\d{2}/', '__YEAR__')
    )
    ->replace($content);

  $file->setContent($content);
  return $file;
});

File::runDirectoryTasks('/path/to/dir');
```

**Performance Results**: In tests with 5,000 files across 100 directories performing 10 operations per file:

- Traditional approach: ~16s (multiple directory scans, multiple I/O per file)
- Batch approach: ~1.7s (**~89% faster**, single directory scan, single I/O per file)

#### Architecture

The batch operations are powered by an internal `Tasker` queue management system that:

- Uses PHP generators for memory-efficient processing of large file sets
- Implements a two-way communication pattern between the queue and file processors
- Leverages `ContentFile` objects for file content manipulation
- Provides type-safe object validation to ensure data integrity
- Maintains complete separation between the generic queue system and file-specific operations

This architecture allows the library to scale efficiently from small single-file operations to large-scale batch processing scenarios.

### Content Replacement

The `Replacer` class provides pattern-based content replacement in files and directories. It's particularly useful for normalizing volatile content like version numbers, hashes, and timestamps.

#### Basic Usage

```php
use AlexSkrypnyk\File\Replacer\Replacement;
use AlexSkrypnyk\File\Replacer\Replacer;

// Use preset version patterns.
$replacer = Replacer::create()
  ->addVersionReplacements()
  ->setMaxReplacements(0);

$replacer->replaceInDir($directory);

// Or create a custom replacer.
$replacer = Replacer::create()
  ->addReplacement(
    Replacement::create('version', '/v\d+\.\d+\.\d+/', '__VERSION__')
  )
  ->addReplacement(
    Replacement::create('date', '/\d{4}-\d{2}-\d{2}/', '__DATE__')
  );

// Apply to string content. The content is passed by reference.
$content = 'Version: v1.2.3';
$replacer->replace($content);  // $content is now 'Version: __VERSION__'

// Apply to a directory.
$replacer->replaceInDir($directory);
```

#### Replacement Limit

A replacer stops after **4 rules have matched**. The limit counts rules, not individual matches, so a rule that fires is counted once no matter how many occurrences it rewrites.

This default matters most with `addVersionReplacements()`, which registers 12 rules: on content that triggers more than four of them, the later rules never run. Call `setMaxReplacements(0)` to lift the limit, or pass a per-call override to `replace()`:

```php
// Apply every rule that matches.
$replacer->setMaxReplacements(0);

// Or override the limit for a single call.
$replacer->replace($content, 0);
```

#### Version Patterns Preset

`addVersionReplacements()` adds the following rules, in this order. Rules are applied in the order they were added, so `docker_digest` is registered before `docker_tag` to claim digest-pinned images first.

| Name                   | Example input                            | Output                                    |
|------------------------|------------------------------------------|-------------------------------------------|
| `integrity`            | `sha512-<86 chars>`                      | `__INTEGRITY__`                           |
| `gha_digest_versioned` | `actions/checkout@<40-hex> # v4.1.2`     | `actions/checkout@__HASH__ # __VERSION__` |
| `gha_digest`           | `actions/checkout@<40-hex>`              | `actions/checkout@__HASH__`               |
| `hash_anchor`          | `repo#<40-hex>`                          | `repo#__HASH__`                           |
| `hash_at`              | `repo@<40-hex>`                          | `repo@__HASH__`                           |
| `json_version`         | `"dep": "^1.2.3"`                        | `"dep": "__VERSION__"`                    |
| `docker_digest`        | `library/nginx:1.21.0@sha256:<64-hex>`   | `library/nginx:__VERSION__`               |
| `docker_tag`           | `library/nginx:1.21.0`                   | `library/nginx:__VERSION__`               |
| `docker_canary`        | `some/image:canary`                      | `some/image:__VERSION__`                  |
| `gha_version`          | `actions/checkout@v4`                    | `actions/checkout@__VERSION__`            |
| `node_version`         | `node-version: 20.1.0`                   | `node-version: __VERSION__`               |
| `semver`               | `1.2.3`, `v1.2.3-beta.1`                 | `__VERSION__`                             |

The `__VERSION__`, `__HASH__` and `__INTEGRITY__` placeholders are the `ReplacementInterface::VERSION`, `HASH` and `INTEGRITY` constants.

#### Exclusions

Add exclusions to prevent specific matches from being replaced:

```php
$replacer = Replacer::create()
  ->addVersionReplacements()
  ->setMaxReplacements(0)
  // Do not replace 0.0.x versions.
  ->addExclusions(['/^0\.0\./'], 'semver');

// Or add exclusions to every rule.
$replacer->addExclusions(['127.0.0.1']);

// Exclusions can be:
// - Regex patterns: '/^0\./'
// - Exact strings: '127.0.0.1'
// - Callbacks: fn(string $match): bool => $match === '9.9.9'
```

Passing an empty array clears the exclusions from the targeted rules, and naming a rule that does not exist throws an `InvalidArgumentException`.

#### Custom Replacements

Create custom replacement patterns:

```php
$replacer = Replacer::create()
  ->addReplacement(
    Replacement::create('build', '/BUILD-\d+/', '__BUILD__')
  )
  ->addReplacement(
    Replacement::create('timestamp', '/\d{10}/', '__TIMESTAMP__')
  )
  // 0 = unlimited replacements.
  ->setMaxReplacements(0);

$replacer->replaceInDir($directory, ['/path/to/ignore']);
```

A replacement is keyed by its name, so adding a second replacement under an existing name overwrites the first. A matcher can also be a closure that receives and returns the whole content, in which case the replacement string and any exclusions are not used.

#### Sharing a Replacer with the File class

By default, `File` builds a fresh `Replacer` for each call so replacements never accumulate across operations. Pass a configured instance to reuse one across calls:

| Method                | Description                                                    |
|-----------------------|----------------------------------------------------------------|
| `File::setReplacer()` | Use the given `Replacer` for subsequent `File` operations.      |
| `File::getReplacer()` | Get the shared instance, or a fresh one when none is set.       |
| `File::resetReplacer()` | Restore the default fresh-instance-per-call behaviour.        |

### Assertion Traits

The library includes PHPUnit traits for testing files and directories. They are compatible with PHPUnit 11.

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
    // Assert directory contains "example" in at least one file.
    $this->assertDirectoryContainsString('/path/to/dir', 'example');

    // Assert the same, ignoring specific files.
    $this->assertDirectoryContainsString(
      '/path/to/dir',
      'example',
      ['temp.log', 'cache']
    );

    // Assert directory does not contain a specific word.
    $this->assertDirectoryNotContainsWord('/path/to/dir', 'forbidden');
  }
}
```

##### Ignoring Paths in Directory Assertions

The directory assertion methods support ignoring specific paths during searches. You can ignore paths in two ways:

1. **Per-method ignoring**: Pass an `$ignored` array parameter to individual assertion methods
2. **Global ignoring**: Override the `ignoredPaths()` method in your test class

```php
class MyTest extends TestCase {
  use DirectoryAssertionsTrait;

  // Global ignored paths for all directory assertions in this class.
  public static function ignoredPaths(): array {
    return ['.git', 'node_modules', 'vendor', 'temp/cache'];
  }

  public function testWithIgnoredPaths(): void {
    // Ignores both the global paths and the 'logs' directory.
    $this->assertDirectoryContainsString(
      '/path/to/dir',
      'search_term',
      ['logs']
    );

    // Global ignored paths apply to every directory assertion.
    $this->assertDirectoryNotContainsWord('/path/to/dir', 'forbidden');
  }
}
```

**Important Notes:**

- Ignored paths are literal subpaths (not wildcard patterns)
- Global `ignoredPaths()` and per-method `$ignored` parameters are merged together
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
    // Assert file contains "example" string.
    $this->assertFileContainsString('/path/to/file.txt', 'example');

    // Assert file contains "test" as a complete word.
    $this->assertFileContainsWord('/path/to/file.txt', 'test');

    // Assert file does not contain a partial word.
    $this->assertFileNotContainsWord('/path/to/file.txt', 'exampl');

    // Assert two files have identical content.
    $this->assertFileEqualsFile('/path/to/a.txt', '/path/to/b.txt');

    // Assert two files have different content.
    $this->assertFileNotEqualsFile('/path/to/a.txt', '/path/to/b.txt');

    // Assert that multiple files exist in a directory.
    $this->assertFilesExist('/path/to/dir', ['file1.txt', 'file2.txt']);

    // Assert that multiple files do not exist in a directory.
    $this->assertFilesDoNotExist('/path/to/dir', ['file1.txt']);

    // Assert that files matching wildcard pattern(s) exist.
    $this->assertFilesWildcardExists('*.txt');
    $this->assertFilesWildcardExists(['*.txt', '*.json']);

    // Assert that files matching wildcard pattern(s) do not exist.
    $this->assertFilesWildcardDoNotExist('*.log');
    $this->assertFilesWildcardDoNotExist(['*.tmp', '*.cache']);

    // Every assertion accepts an optional custom failure message.
    $this->assertFileContainsString(
      '/path/to/file.txt',
      'example',
      'Custom failure message'
    );
    $this->assertFilesExist(
      '/path/to/dir',
      ['file1.txt'],
      'Files should exist'
    );
  }
}
```

Both traits can be used together in the same test class when a test needs file and directory assertions side by side.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for local development setup and the linting, testing and benchmarking commands.

## Updating

To pull the latest infrastructure from the template into this project, ask Claude Code to "update scaffold" - see [`AGENTS.md`](AGENTS.md) for details.

---
_This repository was created using the [Scaffold](https://getscaffold.dev/) project template_
