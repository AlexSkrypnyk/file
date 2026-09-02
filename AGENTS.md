# AGENTS.md

This file provides guidance to AI agents when working with
code in this repository.


## Project Overview

A PHP 8.2+ library of file and directory manipulation utilities, consumed by
other projects via `composer require alexskrypnyk/file`. It also ships PHPUnit
assertion traits so consuming projects can assert on file and directory state in
their own tests.


## PHP Application Architecture


### Class Library

This project ships classes only - there is no CLI entry point:

- **Location:** `src/` directory, autoloaded PSR-4
- **Consumed by:** other projects, via `composer require`

Add classes under `src/` and cover each one with a test in `tests/Unit/`.


### Key Classes

- `File` - static utilities for file and directory manipulation
- `ContentFile` - file object with mutable content for batch processing,
  extends `SplFileInfo`
- `ContentFileInterface` - interface for file objects with mutable content
- `Tasker` - queue management for batch operations
- `Replacer` - token replacement across file trees
- `DirectoryAssertionsTrait`, `FileAssertionsTrait` (`src/Testing/`) - PHPUnit
  assertions for directory and file state


### Namespace Structure

- Source code: `AlexSkrypnyk\File\`
- Tests: `AlexSkrypnyk\File\Tests\`
- Benchmarks: `AlexSkrypnyk\File\Benchmarks\`
- Autoloading: PSR-4 via Composer

## Commands

### Code Quality

```bash
# Run all linters (PHPCS, PHPStan, Rector)
composer lint

# Auto-fix code style issues
composer lint-fix

# Individual tools
./vendor/bin/phpcs # Check coding standards
./vendor/bin/phpcbf # Fix coding standards
./vendor/bin/phpstan # Static analysis (level 9)
./vendor/bin/rector --dry-run # Check Rector suggestions
```

Run `composer lint` until it passes. CI fails on any violation unless the
`CI_LINT_IGNORE_FAILURE` repository variable is set to `1`.

### Testing

```bash
# Run all PHPUnit tests (fast, no coverage)
composer test

# Run with coverage reports
composer test-coverage
# Coverage reports: .logs/.coverage-html/index.html, .logs/cobertura.xml

# Run specific test file
./vendor/bin/phpunit tests/Unit/FileTest.php

# Run specific test method
./vendor/bin/phpunit --filter testMethodName
```

### Benchmarking

```bash
# Run benchmarks against the stored baseline (used by CI)
composer benchmark

# Create or update the baseline
composer benchmark-baseline

# Run a single benchmark class
./vendor/bin/phpbench run benchmarks/TaskBench.php --ref=baseline

# Run with detailed output
./vendor/bin/phpbench run --report=aggregate

# Verify a benchmark runs without paying for the full suite
./vendor/bin/phpbench run benchmarks/TaskBench.php --iterations=1 --revs=1
```

Reports are written to `.logs/performance-report.*` as JSON, CSV and HTML.

### Building

```bash
# Clean and reinstall dependencies
composer reset # removes vendor/, composer.lock
composer install
```

## Code Quality Standards

### Three-Layer Quality Stack

1. **PHP_CodeSniffer** - Drupal coding standards + strict types requirement
  - Config: `phpcs.xml`
  - Rules: Drupal standard, DrevOps standard, Generic.PHP.RequireStrictTypes
  - Relaxed rules in test files (long arrays, missing function docs)

2. **PHPStan** - Level 9 static analysis
  - Config: `phpstan.neon`
  - Ignores: untyped iterables in tests and data providers, PHPBench attributes

3. **Rector** - PHP 8.2 modernization + code quality
  - Config: `rector.php`
  - Sets: PHP_82, CODE_QUALITY, CODING_STYLE, DEAD_CODE,
    TYPE_DECLARATION

### Coding Conventions

- All PHP files must declare `strict_types=1`
- Use single quotes for strings (double quotes if containing single quote)
- All files must end with a newline character
- Local variables/method arguments: `snake_case`
- Method names/class properties: `camelCase`
- `NULL`, `TRUE` and `FALSE` are uppercase
- A catch block's variable name matches the exception type
  (`FileException $fileException`)
- Every class carries a doc comment; inline comments end with punctuation

### Recurring PHPStan Fixes

- Narrow `file_get_contents()` with `$this->assertIsString($content, 'message')`
- Remove always-true conditions in loops; use a constant instead
- Prefer a specific assertion over `assertTrue(TRUE, ...)`
- Suppress with `// @phpstan-ignore-next-line`, not a docblock

## Testing Patterns

### PHPUnit Structure

- `tests/Unit/` - unit tests
- `tests/Fixtures/` - fixture trees the tests copy and operate on

### Writing Tests

Tests should use PHPUnit 11 features:

- Coverage attributes: `#[CoversClass(ClassName::class)]`
- Test attributes: `#[Test]` (optional, using `test` prefix is also fine)
- Data providers: `#[DataProvider('providerMethodName')]`

`LocationsTrait` from `alexskrypnyk/phpunit-helpers` manages the per-test
directories.


## CI/CD

GitHub Actions workflows test across:

- PHP versions: 8.2, 8.3, 8.4, 8.5
- Dependency sets: `normal` and `lowest`
- Lint, coverage threshold and Codecov upload run on the 8.4 / `normal` leg only

Key workflows:

- `.github/workflows/test-php.yml` - PHP testing
- `.github/workflows/benchmark-php.yml` - PHPBench, comparing against the
  baseline in `.phpbench/storage/` with a ±5% threshold. The baseline is
  committed on pushes to `main`; regressions beyond the threshold fail the run.
  Benchmarks run without xdebug or pcov so timings are not distorted.


## Updating from the template

This project was generated from a template and can pull the template's latest
infrastructure (CI workflows, linting and test configuration, Docker, and docs)
at any time. When you are asked to "update scaffold", fetch the updater skill
from the template repository into the local `.claude/skills/` directory, then
invoke it:

1. Create the target directory if it does not exist:

   ```bash
   mkdir -p .claude/skills/update-consumer-scaffold
   ```

2. Download the skill:

   ```bash
   curl -sSL https://raw.githubusercontent.com/AlexSkrypnyk/scaffold/main/.scaffold/skills/update-consumer-scaffold/SKILL.md -o .claude/skills/update-consumer-scaffold/SKILL.md
   ```

3. Invoke the `update-consumer-scaffold` skill and follow its steps.

The skill directory is fetched on demand and is git-ignored - it is not
committed to the project.
