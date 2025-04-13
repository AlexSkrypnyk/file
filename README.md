<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="https://placehold.jp/000000/ffffff/200x200.png?text=File&css=%7B%22border-radius%22%3A%22%20100px%22%7D" alt="Logo logo"></a>
</p>

<h1 align="center">File manipulations</h1>

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

## Installation

```bash
composer require alexskrypnyk/file
```

## Usage

This library provides a set of static methods for file and directory operations.
All methods are available through the `AlexSkrypnyk\File\File` class.

```php
use AlexSkrypnyk\File\File;

// Get current working directory
$cwd = File::cwd();

// Copy a directory recursively
File::copy('/path/to/source', '/path/to/destination');

// Check if a file contains a string
if (File::contains('/path/to/file.txt', 'search term')) {
  // Do something
}
```

### Available Functions

| Function                | Description                                                                        |
|-------------------------|------------------------------------------------------------------------------------|
| `absolute()`            | Get absolute path for provided absolute or relative file.                          |
| `compare()`             | Compare files between source and destination directories.                          |
| `contains()`            | Check if file contains a specific string or matches a pattern.                     |
| `containsInDir()`       | Find all files in directory containing a specific string.                          |
| `copy()`                | Copy file or directory.                                                            |
| `copyIfExists()`        | Copy file or directory if it exists.                                               |
| `cwd()`                 | Get current working directory with absolute path.                                  |
| `diff()`                | Create diff files between baseline and destination directories. See [Diff Operations](#diff-operations) below. |
| `dir()`                 | Get absolute path for existing directory.                                          |
| `dirIsEmpty()`          | Check if directory is empty.                                                       |
| `dump()`                | Write content to a file.                                                           |
| `exists()`              | Check if file or directory exists.                                                 |
| `findMatchingPath()`    | Find first path that matches a needle among provided paths.                        |
| `mkdir()`               | Creates a directory if it doesn't exist.                                           |
| `patch()`               | Apply patch files to a baseline and produce a destination.                         |
| `read()`                | Read file contents.                                                                |
| `realpath()`            | Replacement for PHP's `realpath` resolves non-existing paths.                      |
| `remove()`              | Remove file or directory.                                                          |
| `removeLine()`          | Remove lines containing a specific string from a file.                             |
| `removeToken()`         | Remove tokens and optionally content between tokens from a file.                   |
| `removeTokenInDir()`    | Remove tokens and optionally content between tokens from all files in a directory. |
| `renameInDir()`         | Rename files in directory by replacing part of the filename.                       |
| `replaceContent()`      | Replace content in a file.                                                         |
| `replaceContentInDir()` | Replace content in all files in a directory.                                       |
| `rmdir()`               | Remove directory recursively.                                                      |
| `rmdirEmpty()`          | Remove directory recursively if empty.                                             |
| `scandirRecursive()`    | Recursively scan directory for files.                                              |
| `sync()`                | Synchronize files from source to destination directory.                            |
| `tmpdir()`              | Create temporary directory.                                                        |

### Diff Operations

The `diff()`, `patch()`, and `compare()` functions provide powerful tools for working with file differences between directories:

```php
use AlexSkrypnyk\File\File;

// Generate diff files between baseline and destination directories
File::diff('/path/to/baseline', '/path/to/destination', '/path/to/diff');

// Compare directories to determine if they're equal
$result = File::compare('/path/to/source', '/path/to/destination');

// Apply patches to transform a baseline directory
File::patch('/path/to/baseline', '/path/to/diff', '/path/to/patched');
```

The diff functionality allows you to:
1. Generate differences between two directory structures
2. Store those differences as patch files
3. Apply those patches to recreate directory structures elsewhere

#### Ignoring Files and Content Changes

You can create a `.ignorecontent` file in your directories to specify patterns for files or content that should be ignored during comparison. This is useful for timestamps, randomly generated values, or files that shouldn't be compared.

The syntax for `.ignorecontent` file is similar to `.gitignore` with additional content ignoring capabilities:

```
# Comments start with #
file.txt        # Ignore this specific file
logs/           # Ignore this directory and all subdirectories
temp/*          # Ignore all files in directory, but not subdirectories
^config.json    # Ignore content changes in this file, but check file exists
^data/          # Ignore content changes in all files in dir and subdirs
^cache/*        # Ignore content changes in all files in dir, but not subdirs
!important.txt  # Do not ignore this file (exception)
!^settings.php  # Do not ignore content changes in this file
```

Prefix meanings:
- No prefix: Ignore file/directory completely
- `^`: Ignore content changes but verify file/directory exists
- `!`: Exception - do not ignore this file/directory
- `!^`: Exception - do not ignore content changes in this file/directory

### Directory Assertions Trait

The library includes a PHPUnit trait for directory testing assertions:

| Assertion Method | Description |
|------------------|-------------|
| `assertDirectoryContainsString()` | Assert that a directory contains files with a specific string. |
| `assertDirectoryNotContainsString()` | Assert that a directory does not contain files with a specific string. |
| `assertDirectoryContainsWord()` | Assert that a directory contains files with a specific word (bounded by word boundaries). |
| `assertDirectoryNotContainsWord()` | Assert that a directory does not contain files with a specific word. |
| `assertDirectoryEqualsDirectory()` | Assert that two directories have identical structure and content. |
| `assertDirectoryEqualsPatchedBaseline()` | Assert that a directory is equal to the patched baseline (baseline + diff). |

Usage example:

```php
use PHPUnit\Framework\TestCase;
use AlexSkrypnyk\File\Tests\Traits\DirectoryAssertionsTrait;

class MyTest extends TestCase {
  use DirectoryAssertionsTrait;
  
  public function testDirectories(): void {
    // Assert directory contains "example" string in at least one file
    $this->assertDirectoryContainsString('example', '/path/to/directory');
    
    // Assert two directories are identical
    $this->assertDirectoryEqualsDirectory('/path/to/dir1', '/path/to/dir2');
  }
}
```

## Maintenance

```bash
composer install
composer lint
composer test
```

---
_This repository was created using the [Scaffold](https://getscaffold.dev/)
project template_
