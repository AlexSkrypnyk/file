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
  - [Symlinks](#symlinks)
  - [Batch Operations](#batch-operations)
  - [Content Replacement](#content-replacement)
  - [Assertion Traits](#assertion-traits)
    - [Directory Assertions Trait](#directory-assertions-trait)
    - [File Assertions Trait](#file-assertions-trait)
- [Contributing](#contributing)
- [Updating](#updating)

## Installation

Requires PHP 8.3 or newer. Supports `symfony/filesystem` 7.4 and 8.x. Symfony 8 requires PHP 8.4 or newer.

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
| `isPathInside()`             | Check if a path is a directory or lies inside it.                                  |
| `mkdir()`                    | Creates a directory if it doesn't exist.                                           |
| `read()`                     | Read file contents.                                                                |
| `readSymlink()`              | Read a symlink target, relative to a root when the target is inside it.            |
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
| `toForwardSlashes()`         | Replace Windows directory separators with forward slashes.                         |

Methods that accept a needle also accept a regular expression. A needle is treated as a pattern when it starts with one of the `/`, `#`, `~`, `@` or `%` delimiters and is a valid expression, so `'/\.php$/'` matches by pattern while `'text'` matches literally.

The directory-wide methods skip the paths returned by `ignoredPaths()` - `/.git/`, `/.idea/`, `/vendor/` and `/node_modules/`. The methods that rewrite the content of a single file additionally skip images: `.png`, `.jpg`, `.jpeg`, `.bmp` and `.tiff`.

### Symlinks

`File` treats symlinks the same way on Linux, macOS and Windows. The following table lists what each method does with a symlink:

| Method                | What it does with a symlink                                                                                                                                                          |
|-----------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `copy()`              | Copies the symlink as a symlink, after every file and directory is copied. The symlink mode sets its target.                                                                        |
| `scandir()`           | Lists a file symlink or a dangling symlink as a file. Lists a directory symlink as a directory, only when directories are included. Walks a directory symlink by default and lists its files under the symlink path. Stops at a symlink to a directory the walk is already inside, so a loop can't recurse. |
| `remove()`, `rmdir()` | Removes the symlink and leaves its target.                                                                                                                                           |
| `rmdirIfEmpty()`      | Leaves a directory symlink in place, even when its target is empty.                                                                                                                  |
| `exists()`            | Returns `FALSE` for a dangling symlink.                                                                                                                                              |
| `realpath()`          | Follows the chain of symlinks to the final target. A relative target resolves against its symlink's directory, and a dangling symlink resolves to its target path. A loop throws a `FileException`. |
| `readSymlink()`       | Reads the target of 1 symlink, relative to a root you pass.                                                                                                                          |

`absolute()`, `dir()` and `mkdir()` resolve a path with `realpath()`, so they follow symlinks the same way. The directory-wide methods, such as `replaceContentInDir()`, get their files from `scandir()`, so they reach the files behind a directory symlink too. `dirIsEmpty()` counts those files as well, so `rmdirIfEmpty()` keeps a directory that holds a symlink to a non-empty directory.

#### Scan without following symlinks

To list directory symlinks without walking them, call `File::setFollowSymlinks(FALSE)` before you scan. The directory-wide methods then skip the files behind each directory symlink:

```php
use AlexSkrypnyk\File\File;

File::setFollowSymlinks(FALSE);
File::replaceContentInDir('/path/to/tree', 'old', 'new');
```

`File::getFollowSymlinks()` returns the current setting, and `File::resetFollowSymlinks()` restores the default of following symlinks.

The setting applies to every later scan in the process. A library that scans with its own setting passes it to `scandir()` instead. That setting applies to that call only and leaves the global setting unchanged:

```php
File::scandir('/path/to/tree', follow_symlinks: FALSE);
```

#### Copy symlinks

`copy()` creates every symlink after the files and directories are in place, so a symlink and its target can come in any order. Pick how it sets each symlink's target:

| Mode                                    | The copied symlink points at                                                                                                                          |
|-----------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------|
| `File::SYMLINK_MODE_NATIVE` (default)   | The target the platform reads. Linux and macOS read the stored target. Windows reads the resolved absolute target, which points into the source tree. |
| `File::SYMLINK_MODE_RELATIVE`           | A target inside the copied tree, as a path relative to the symlink, on every platform. A target outside the copied tree becomes an absolute path.      |

To make the copied tree self-contained, so its symlinks keep working after the source tree is removed, set the relative mode before copying:

```php
use AlexSkrypnyk\File\File;

File::setSymlinkMode(File::SYMLINK_MODE_RELATIVE);
File::copy('/path/to/source', '/path/to/destination');
```

`File::getSymlinkMode()` returns the current mode, and `File::resetSymlinkMode()` restores the default. `setSymlinkMode()` throws a `FileException` for a value that isn't 1 of the 2 constants.

The mode set with `setSymlinkMode()` applies to every later copy in the process. A library that copies with its own mode passes it to `copy()` instead. That mode applies to that call only and leaves the global mode unchanged:

```php
File::copy('/path/to/source', '/path/to/destination', symlink_mode: File::SYMLINK_MODE_RELATIVE);
```

To copy only part of a directory, pass a filter to `copy()`. The filter receives each entry's path relative to the source and whether the entry is a directory. Paths use `/` separators on every platform. A symlink counts as a file. Return `FALSE` to skip the entry. A skipped directory isn't entered, so its whole subtree is skipped:

```php
File::copy('/path/to/source', '/path/to/destination', filter: fn(string $path, bool $is_dir): bool => !in_array($path, ['.git', 'vendor'], TRUE));
```

To copy an exact selection, pass the list of paths instead of a callback. `copy()` enters the parent directories of each listed path, and copies a listed directory with its whole subtree:

```php
File::copy('/path/to/source', '/path/to/destination', filter: ['hooks/run.sh', 'library'], symlink_mode: File::SYMLINK_MODE_RELATIVE);
```

A filtered copy still treats the source as the tree for the relative mode. A symlink whose target is filtered out, or is already missing in the source, is copied as a dangling symlink. It resolves after the target exists. When `copy()` can't read or create a symlink, it throws a `FileException` that names the symlink.

#### Read symlinks

To read a symlink's target the same way on every platform, pass the root of its tree to `readSymlink()`. A target inside the root comes back relative to the symlink's directory with `/` separators, and a target outside it comes back absolute. The result doesn't depend on the symlink mode. Without a root, `readSymlink()` returns the target the platform reads, and it returns `FALSE` when the target can't be read:

```php
File::readSymlink('/path/to/tree/hooks/link', '/path/to/tree');
// '../library/provision.sh'
```

To get the final path a symlink leads to, use `realpath()`:

```php
// /path/to/tree/current -> release, and /path/to/tree/latest -> current.
File::realpath('/path/to/tree/latest');
// '/path/to/tree/release'
```

#### Symlinks on Windows

PHP on Windows reads symlinks differently from Linux and macOS. `File` works around each difference:

- `readlink()` returns the resolved absolute target instead of the stored one. `File` makes a target relative again in the relative mode and in `readSymlink()`.
- `readlink()`, `realpath()` and `is_link()` can't read a dangling relative symlink. `File` reads its stored target from the `dir /AL` listing of its directory.
- `symlink()` refuses a target that doesn't exist yet. `File` creates a dangling symlink with the `mklink` command.
- `realpath()` returns the missing target of a dangling absolute symlink. `File` checks that the target exists before it trusts the result.
- An archive extractor such as `tar` creates a directory symlink as a file symlink when its target isn't extracted yet. `File` reads it with `realpath()` and copies it as a directory symlink.

Reading and creating a dangling symlink on Windows runs `dir /AL` and `mklink` through `proc_open()`. When `proc_open()` is disabled, copying a dangling symlink throws a `FileException`. Paths compare case-insensitively on Windows. Creating any symlink on Windows needs Developer Mode or administrator rights. Junctions aren't supported.

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
