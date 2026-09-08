# Contributing

Thank you for considering a contribution to this project. This guide covers setting up a local environment, running the linting and tests, and running the benchmarks.

## Local setup

Requires PHP 8.3 or newer and Composer.

```bash
composer install
```

## Linting

`composer lint` runs PHP_CodeSniffer, PHPStan at level 9, and Rector in dry-run mode. CI fails on any violation unless the `CI_LINT_IGNORE_FAILURE` repository variable is set to `1`, which makes the lint step non-blocking. The test step has the same escape hatch in `CI_TEST_IGNORE_FAILURE`.

```bash
composer lint
composer lint-fix
```

## Testing

```bash
composer test
composer test-coverage
```

Coverage reports are written to `.logs/.coverage-html/index.html` and `.logs/cobertura.xml`.

Run a single file or a single test method with PHPUnit directly:

```bash
./vendor/bin/phpunit tests/Unit/FileTest.php
./vendor/bin/phpunit --filter testMethodName
```

## Benchmarking

`composer benchmark` runs PHPBench once and reports the timings. There is no stored baseline: a comparison measures both revisions on the machine it runs on, because the spread between two hosts is several times larger than the change most benchmarks are meant to detect.

`composer benchmark-compare` measures two checkouts back to back and asserts that no subject in the head one got slower by more than the threshold, which defaults to 15%.

Give the two checkouts names of the same length and the same toolchain. A subject that resolves against the working directory pays for every character of that path, so a name 20 characters longer costs about as much as four extra directory levels, and the run would report that as a difference between the revisions. The script warns when the two paths differ in length.

```bash
composer benchmark

mkdir -p .artifacts/bench
git clone -q --no-hardlinks . .artifacts/bench/base
git -C .artifacts/bench/base checkout main
git clone -q --no-hardlinks . .artifacts/bench/head
cp -R vendor .artifacts/bench/base/vendor
cp -R vendor .artifacts/bench/head/vendor
composer benchmark-compare -- --base=.artifacts/bench/base --head=.artifacts/bench/head
```

Reports are written to `.logs/performance-report.*` as JSON, CSV and HTML.

## Coding standards

- All PHP files declare `strict_types=1`.
- Local variables and method arguments use `snake_case`; method names and class properties use `camelCase`.
- `NULL`, `TRUE` and `FALSE` are uppercase.
- Single quotes for strings, and every file ends with a newline.

## Rebuilding dependencies

```bash
composer reset
composer install
```
