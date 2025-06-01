# Claude Code Configuration

## Project Structure
- PHP 8.2+ library for file operations
- PHPUnit for testing
- LocationsTrait provides directory management during tests
- DirectoryAssertionsTrait provides assertions for directory operations
- FileAssertionsTrait provides assertions for file operations

## Testing Commands
```bash
# Run all tests (excludes performance tests)
composer test

# Run tests with coverage
composer test-coverage

# Run performance tests only
./vendor/bin/phpunit --testsuite=performance
./vendor/bin/phpunit tests/Unit/FileTaskPerformanceTest.php
```

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
