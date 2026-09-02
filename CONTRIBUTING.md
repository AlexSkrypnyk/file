# Contributing

Thank you for considering a contribution to this project. This guide covers setting up a local environment, running the linting and tests, and running the benchmarks.

## Local setup

Requires PHP 8.2 or newer and Composer.

```bash
composer install
```

## Linting

`composer lint` runs PHP_CodeSniffer, PHPStan at level 9, and Rector in dry-run mode. CI fails on any violation.

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

`composer benchmark` runs PHPBench against the stored baseline in `.phpbench/storage/`. CI fails a run that regresses beyond ±5%.

```bash
composer benchmark
composer benchmark-baseline
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
