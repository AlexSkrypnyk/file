# Performance Testing Implementation Plan for File::compare()

## Overview
Implement PHPBench-based performance testing for the `File::compare()` method, following the pattern established in the drevops/environment-detector project (commit f4a13a1).

## Current State Analysis
- ❌ Current `FileTaskPerformanceTest.php` uses PHPUnit (not following PHPBench pattern)
- ✅ Target method: `File::compare()` at File.php:1157
- ✅ phpunit.xml already has performance test suite configuration
- ❌ Missing PHPBench dependency and infrastructure

## Implementation Tasks

### 1. Add PHPBench Dependency
**File**: `composer.json`
- Add to `require-dev`: `"phpbench/phpbench": "^1.4"`
- Run: `composer update phpbench/phpbench`

### 2. Update Composer Autoload Configuration
**File**: `composer.json`
- Add to `autoload-dev.psr-4`:
  ```json
  "AlexSkrypnyk\\File\\Benchmarks\\": "benchmarks"
  ```
- Run: `composer dump-autoload`

### 3. Create Benchmarks Directory
**Action**: Create directory `benchmarks/`

### 4. Create Benchmark Class for File::compare()
**File**: `benchmarks/FileCompareBench.php`

**Structure**:
```php
<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Benchmarks;

use AlexSkrypnyk\File\File;
use PHPBench\Attributes as Bench;

class FileCompareBench {
  protected string $tmpDir;
  protected string $baselineDir;
  protected string $destinationDir;

  // Constants for test data
  protected const FILE_COUNT = 1000;
  protected const DIR_COUNT = 50;
  protected const MAX_DEPTH = 5;
  protected const LARGE_FILE_SIZE = 1048576; // 1MB

  // Setup method to create test data
  public function setUp(): void

  // Teardown method to clean up
  public function tearDown(): void

  // Benchmark methods with PHPBench attributes
  #[Bench\Revs(100)]
  #[Bench\Warmup(2)]
  public function benchCompareIdenticalDirectories(): void

  #[Bench\Revs(50)]
  #[Bench\Warmup(2)]
  public function benchCompareWithContentDiffs(): void

  #[Bench\Revs(50)]
  #[Bench\Warmup(2)]
  public function benchCompareWithStructuralDiffs(): void

  #[Bench\Revs(20)]
  #[Bench\Warmup(2)]
  public function benchCompareWithLargeFiles(): void

  #[Bench\Revs(30)]
  #[Bench\Warmup(2)]
  public function benchCompareWithDeepNesting(): void

  #[Bench\Revs(50)]
  #[Bench\Warmup(2)]
  public function benchCompareWithRules(): void

  #[Bench\Revs(50)]
  #[Bench\Warmup(2)]
  public function benchCompareWithCallback(): void

  // Helper methods
  protected function createIdenticalDirectories(): void
  protected function createDirectoryWithContentDiffs(int $percent_changed): void
  protected function createDirectoryWithStructuralDiffs(): void
  protected function createDeepNestedStructure(int $depth): void
  protected function createLargeFiles(int $size): void
}
```

### 5. Test Scenarios

#### Scenario 1: Identical Directories
- Purpose: Baseline performance
- Setup: Create 1000 files across 50 directories
- Both baseline and destination are identical
- Measures: Pure comparison overhead

#### Scenario 2: Content Differences
- Purpose: Diff detection performance
- Setup: 1000 files, 20% have content modifications
- Measures: Content comparison and diff generation

#### Scenario 3: Structural Differences
- Purpose: Missing/extra file detection
- Setup: Baseline has 1000 files, destination missing 10%, has 10% extra
- Measures: Structural analysis performance

#### Scenario 4: Large Files
- Purpose: Performance with large file sizes
- Setup: 100 files ranging from 1KB to 10MB
- Measures: Memory usage and comparison time

#### Scenario 5: Deep Nesting
- Purpose: Performance with deep directory structures
- Setup: 500 files nested 10-15 levels deep
- Measures: Recursive scanning overhead

#### Scenario 6: With Rules
- Purpose: Rules object impact
- Setup: 1000 files with custom Rules filtering
- Measures: Rule application overhead

#### Scenario 7: With Callback
- Purpose: before_match_content callback impact
- Setup: 1000 files with content preprocessing callback
- Measures: Callback execution overhead

### 6. Configuration Files

**File**: `phpbench.json` (create if needed)
```json
{
  "$schema": "./vendor/phpbench/phpbench/phpbench.schema.json",
  "runner.bootstrap": "vendor/autoload.php",
  "runner.path": "benchmarks",
  "report.generators": {
    "default": {
      "generator": "table",
      "sort": ["benchmark"]
    }
  },
  "report.outputs": {
    "html": {
      "output": ".logs/performance-report.html"
    },
    "json": {
      "output": ".logs/performance-report.json"
    }
  }
}
```

### 7. Update Composer Scripts

**File**: `composer.json`
- Add to `scripts` section:
  ```json
  "bench": "phpbench run --report=default",
  "bench-report": "phpbench run --report=default --output=html"
  ```

### 8. Update Documentation

**File**: `CLAUDE.md`
- Add performance testing section:
  ```markdown
  ## Performance Testing Commands
  ```bash
  # Run all benchmarks
  composer bench

  # Run benchmarks with HTML report
  composer bench-report

  # Run specific benchmark class
  ./vendor/bin/phpbench run benchmarks/FileCompareBench.php

  # Run with detailed output
  ./vendor/bin/phpbench run --report=aggregate
  ```

### 9. Update .gitignore (if needed)

**File**: `.gitignore`
- Ensure performance reports are ignored:
  ```
  .logs/performance-report.*
  ```

### 10. Performance Metrics to Track

Each benchmark should measure:
- **Time**: Mean, median, best, worst execution time
- **Memory**: Peak memory usage
- **Iterations**: Number of revolutions configured
- **Mode**: Time per iteration
- **RStDev**: Relative standard deviation (consistency)

### 11. Expected Performance Baselines

Document initial benchmarks as baselines for regression detection:
- Identical directories: < 500ms for 1000 files
- Content diffs (20%): < 800ms for 1000 files
- Structural diffs: < 600ms for 1000 files
- Large files (10MB): < 2s for 100 files
- Deep nesting (15 levels): < 1s for 500 files

### 12. Testing the Implementation

```bash
# Install dependencies
composer install

# Run benchmarks
composer bench

# Verify output in .logs/
ls -la .logs/performance-report.*

# Check HTML report
open .logs/performance-report.html
```

## Success Criteria

- ✅ PHPBench dependency installed
- ✅ Benchmark class created with 7+ benchmark methods
- ✅ All benchmarks run without errors
- ✅ Performance reports generated in .logs/
- ✅ Documentation updated
- ✅ Composer scripts added for easy execution
- ✅ Initial baseline metrics documented

## Notes

- Follow PHPBench best practices for benchmark methods
- Use proper PHPBench attributes (#[Bench\Revs()], #[Bench\Warmup()], etc.)
- Ensure cleanup in tearDown() to prevent disk space issues
- Consider parameterized benchmarks for varying file counts
- Monitor memory usage for large file scenarios

## 13. GitHub Actions Integration for Performance Testing

### Add Separate Performance Testing Job

**File**: `.github/workflows/test-php.yml`

Add a new job that runs independently:

```yaml
test-php-performance:
  runs-on: ubuntu-latest
  steps:
    - name: Checkout code
      uses: actions/checkout@v5

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.3
        coverage: none

    - name: Install dependencies
      run: composer install

    - name: Create logs directory
      run: mkdir -p .logs

    - name: Run performance benchmarks
      run: composer bench-report

    - name: Upload performance reports
      uses: actions/upload-artifact@v5
      with:
        name: performance-report
        path: |
          .logs/performance-report.html
          .logs/performance-report.json
```

**Notes**:
- Runs only on PHP 8.3 (no matrix) to ensure consistent benchmarks
- Disables coverage extensions for accurate performance measurements
- Generates both HTML and JSON reports for different consumers

### 14. Automated Benchmark Results Posting to GitHub Issues

**File**: `.github/workflows/test-php.yml`

Add steps to the `test-php-performance` job to post results to a tracking issue (runs only on push to main):

```yaml
test-php-performance:
  runs-on: ubuntu-latest
  steps:
    # ... previous steps ...

    - name: Find performance tracking issue
      if: github.event_name == 'push' && github.ref == 'refs/heads/main'
      id: find-issue
      env:
        GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
      run: |
        ISSUE_NUMBER=$(gh issue list --label "performance" --state open --search "Performance benchmarks" --json number --jq '.[0].number')
        echo "issue_number=$ISSUE_NUMBER" >> $GITHUB_OUTPUT

    - name: Format benchmark results
      if: github.event_name == 'push' && github.ref == 'refs/heads/main' && steps.find-issue.outputs.issue_number != ''
      run: |
        cat > comment.md <<'EOF'
        ## 🚀 Performance Benchmark Results

        **Commit**: ${{ github.sha }}
        **Timestamp**: $(date -u '+%Y-%m-%d %H:%M:%S UTC')
        **Branch**: ${{ github.ref_name }}

        ### Summary

        Performance benchmarks have been executed. View detailed results in the [artifacts](${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}).

        <details>
        <summary>Quick Results</summary>

        | Benchmark | Time | Memory | Variance |
        |-----------|------|--------|----------|
        | Identical Directories | TBD | TBD | TBD |
        | Content Differences | TBD | TBD | TBD |
        | Structural Differences | TBD | TBD | TBD |
        | Large Files | TBD | TBD | TBD |
        | Deep Nesting | TBD | TBD | TBD |
        | With Rules | TBD | TBD | TBD |
        | With Callback | TBD | TBD | TBD |

        </details>

        ---
        *Automated benchmark results from CI pipeline*
        EOF

    - name: Post results to issue
      if: github.event_name == 'push' && github.ref == 'refs/heads/main' && steps.find-issue.outputs.issue_number != ''
      uses: peter-evans/create-or-update-comment@v4
      with:
        issue-number: ${{ steps.find-issue.outputs.issue_number }}
        body-path: comment.md
```

### 15. Create Performance Tracking Issue

**Manual Task**: Create a GitHub issue with:
- **Title**: "Performance benchmarks"
- **Label**: "performance"
- **Body**:
  ```markdown
  # Performance Benchmark Tracking

  This issue tracks performance benchmark results from CI runs on the main branch.

  ## Purpose
  - Monitor performance trends over time
  - Identify performance regressions
  - Track improvements from optimizations

  ## Benchmark Scenarios
  1. Identical Directories (baseline)
  2. Content Differences (20% changed files)
  3. Structural Differences (missing/extra files)
  4. Large Files (1KB-10MB range)
  5. Deep Nesting (10-15 levels)
  6. With Rules (custom filtering)
  7. With Callback (content preprocessing)

  ---
  *Keep this issue open - CI will post results here automatically*
  ```

### 16. Enhanced Report Parsing (Optional Enhancement)

**File**: `.github/scripts/parse-benchmark-results.php` (create)

For more detailed result extraction from JSON reports:

```php
<?php

$json = file_get_contents('.logs/performance-report.json');
$data = json_decode($json, true);

$markdown = "| Benchmark | Time (μs) | Memory (MB) | Variance (%) |\n";
$markdown .= "|-----------|-----------|-------------|---------------|\n";

foreach ($data['benchmarks'] ?? [] as $benchmark) {
    $name = $benchmark['name'] ?? 'Unknown';
    $time = number_format($benchmark['mean'] ?? 0, 2);
    $memory = number_format(($benchmark['memory'] ?? 0) / 1024 / 1024, 2);
    $variance = number_format($benchmark['rstdev'] ?? 0, 2);

    $markdown .= "| $name | $time | $memory | $variance |\n";
}

file_put_contents('benchmark-table.md', $markdown);
```

Then update the workflow to use this script:

```yaml
- name: Parse benchmark results
  run: php .github/scripts/parse-benchmark-results.php

- name: Format benchmark results
  run: |
    cat > comment.md <<'EOF'
    ## 🚀 Performance Benchmark Results

    **Commit**: ${{ github.sha }}
    **Timestamp**: $(date -u '+%Y-%m-%d %H:%M:%S UTC')

    $(cat benchmark-table.md)
    EOF
```

### 17. Performance Regression Detection (Future Enhancement)

Consider adding automated regression detection:
- Store baseline metrics in repository (e.g., `.benchmarks/baseline.json`)
- Compare current results against baseline
- Fail CI or add warning comment if regression exceeds threshold (e.g., >10% slower)

## References

- PHPBench Documentation: https://phpbench.readthedocs.io/
- Environment Detector PR #19: https://github.com/drevops/environment-detector/pull/19
- Environment Detector PR #25: https://github.com/drevops/environment-detector/pull/25
- PHPBench Attributes: https://phpbench.readthedocs.io/en/latest/guides/benchmark-runner.html
- peter-evans/create-or-update-comment: https://github.com/peter-evans/create-or-update-comment
