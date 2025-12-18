# Claude Code Configuration

## Project Structure
- PHP 8.2+ library for file operations
- PHPUnit for testing
- LocationsTrait provides directory management during tests
- DirectoryAssertionsTrait (`src/Testing/`) provides assertions for directory operations
- FileAssertionsTrait (`src/Testing/`) provides assertions for file operations

## Key Classes
- `File` - Main static class with file manipulation utilities
- `ContentFile` - File object with mutable content for batch processing (extends SplFileInfo)
- `ContentFileInterface` - Interface for file objects with mutable content
- `Tasker` - Internal queue management system for batch operations

## Testing Commands
```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage
```

## Performance Testing Commands (PHPBench)
```bash
# Run benchmarks with baseline comparison (used by CI)
composer benchmark

# Create or update baseline for performance comparison
composer benchmark-baseline

# Run specific benchmark class
./vendor/bin/phpbench run benchmarks/TaskBench.php --ref=baseline

# Run with detailed output
./vendor/bin/phpbench run --report=aggregate

# Quick testing: verify benchmark works without full suite (saves time)
./vendor/bin/phpbench run benchmarks/TaskBench.php --iterations=1 --revs=1
```

### Baseline Management

- Baseline benchmarks are stored in `.phpbench/storage/` directory
- CI compares new benchmarks against baseline with ±5% threshold
- To update baseline manually: `composer benchmark-baseline`
- Baseline updates automatically commit on main branch pushes
- Performance regressions exceeding ±5% will fail CI checks

### Performance Testing

- PHPBench for measuring batch task processing performance
- Benchmarks in `benchmarks/` directory measure:
  - Traditional approach (multiple directory scans)
  - Simple approach (single scan with multiple I/O)
  - Batched approach (single scan with queue system)
- Reports generated as JSON, CSV, and HTML in `.logs/performance-report.*`
- CI runs performance tests without xdebug/pcov for accurate measurements

## Code Standards
- Use snake_case for variable names and function arguments
- Use camelCase for class properties
- Follow Drupal coding standards
- Check coding standards with `composer lint`
- Fix coding standards with `composer lint-fix`
- **CRITICAL**: Always run `composer lint` until it fully passes - CI will fail on any violations
- **PHPStan fixes required**:
  - Handle `file_get_contents()` return type with `$this->assertIsString($content, 'message')`
  - Remove always-true conditions in loops (use proper constants)
  - Use specific assertion methods instead of `assertTrue(TRUE, ...)`
  - Use `// @phpstan-ignore-next-line` (not `/** */`) for intentional issues
- **Class documentation**: All new classes require proper doc comments
- **Comment punctuation**: Inline comments must end with proper punctuation

## Test Coverage Checking
Coverage reports are stored in:
- `.logs/cobertura.xml`
- `.logs/.coverage-html/`

## Development Process Learnings

### Linting and Code Standards
- **Auto-fixing workflow**: Use `composer lint-fix` to automatically fix PHPCS violations, then manually address remaining issues
- **Drupal standards**: NULL constants must be uppercase, variable names in catch blocks should match the exception type name (e.g., `FileException $fileException`)
