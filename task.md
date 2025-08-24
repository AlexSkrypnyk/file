# Task: Implement Callback Variants for Content Processing Methods

## Overview
Add callback-based variants of content processing methods to allow custom processing logic instead of simple string replacement.

## Methods to Implement

### 1. replaceContentCallback(string $content, callable $processor): string
- **Location**: After `replaceContent()` at line 761
- **Purpose**: String-level callback processing (base method)
- **Signature**: `(string $content, callable $processor): string`
- **Callback signature**: `function(string $content): string`

### 2. replaceContentCallbackInFile(string $file, callable $processor): void  
- **Location**: After `replaceContentInFile()` at line 656
- **Purpose**: File-level callback processing
- **Signature**: `(string $file, callable $processor): void`
- **Implementation**: Reads file, calls `replaceContentCallback()`, writes back if changed

### 3. replaceContentCallbackInDir(string $directory, callable $processor): void
- **Location**: After `replaceContentInDir()` at line 629  
- **Purpose**: Directory-level callback processing
- **Signature**: `(string $directory, callable $processor): void`
- **Implementation**: Scans directory, calls `replaceContentCallbackInFile()` for each file

## Implementation Strategy

### Method Implementation
1. Follow exact same patterns as existing methods
2. Use same error handling (file existence, readability, exclusion checks)
3. Use same directory scanning with `ignoredPaths()`
4. Maintain same return types and exception handling

### Exception Handling
1. **Invalid callable validation**: Throw `\InvalidArgumentException` if the `$processor` parameter is not callable
2. **Callback execution errors**: Catch any exceptions thrown by the callback and re-throw as `FileException` with context
3. **Return type validation**: Ensure callback returns string, throw `\InvalidArgumentException` if not
4. **File context**: For file/directory methods, include file path in exception messages for better debugging

### Documentation
- Add proper PHPDoc blocks matching existing style
- Include `@param callable $processor` with signature description
- Include exception documentation where applicable

## Testing Strategy

### Test Structure
- Add tests to `tests/Unit/FileStringsTest.php` (existing test file for content methods)
- Follow existing test patterns with `#[DataProvider]` annotations
- Test both successful processing and error conditions

### Test Cases
1. **replaceContentCallback()**:
   - Basic callback transformation
   - Empty content handling
   - Callback returns same content
   - Callback with complex transformations
   - **Exception cases**: Non-callable parameter, callback returns non-string

2. **replaceContentCallbackInFile()**:
   - File processing with callback
   - Non-existent file handling
   - Excluded file handling
   - File content unchanged after processing
   - **Exception cases**: Invalid callback, callback throws exception

3. **replaceContentCallbackInDir()**:
   - Directory processing with callback
   - Mixed file types in directory
   - Directory with ignored paths
   - **Exception cases**: Invalid callback propagated from file processing

### Data Providers
Create data providers following existing patterns like `dataProviderReplaceContent()`:
- `dataProviderReplaceContentCallback()`
- Include various callback scenarios and expected results

## Documentation Updates

### README.md Updates
1. **Available Functions table** (lines 86-119):
   - Add three new callback methods after their corresponding originals
   
2. **Batch Operations section** (lines 131-133):
   - Add `replaceContentCallback()` as base method for batch processing

3. **Usage examples**:
   - Add callback examples showing practical use cases
   - Demonstrate difference between string replacement and callback processing

## File Structure
- All code changes in `/File.php`
- All test changes in `/tests/Unit/FileStringsTest.php`  
- Documentation changes in `/README.md`

## Validation Steps
1. Run `composer lint` to ensure code standards
2. Run `composer test` to verify all tests pass
3. Verify new methods work with batch operations via `addTaskDirectory()`
4. Check that file exclusion and error handling work correctly