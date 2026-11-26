<?php

/**
 * @file
 * Rector configuration.
 *
 * Usage:
 * ./vendor/bin/rector process .
 */

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;

return RectorConfig::configure()
  ->withPaths([
    __DIR__ . '/benchmarks',
    __DIR__ . '/src',
    __DIR__ . '/tests',
  ])
  ->withPhpSets()
  ->withPreparedSets(
    deadCode: TRUE,
    codeQuality: TRUE,
    codingStyle: TRUE,
    typeDeclarations: TRUE,
    phpunitCodeQuality: TRUE,
  )
  ->withComposerBased(phpunit: TRUE)
  ->withSkip([
    // Rules added by Rector's rule sets.
    CatchExceptionNameMatchingTypeRector::class,
    ChangeSwitchToMatchRector::class,
    ClosureToArrowFunctionRector::class,
    InlineArrayReturnAssignRector::class,
    NewlineBeforeNewAssignSetRector::class,
    NewlineBetweenClassLikeStmtsRector::class,
    RemoveAlwaysTrueIfConditionRector::class,
    SimplifyEmptyCheckOnEmptyArrayRector::class,
    // The anonymous test classes stub 'addToAssertionCount()' for the traits.
    RemoveEmptyClassMethodRector::class => [
      __DIR__ . '/tests/Unit/DirectoryAssertionsTraitTest.php',
    ],
    // Dependencies.
    '*/vendor/*',
    '*/node_modules/*',
  ])
  ->withFileExtensions([
    'php',
    'inc',
  ])
  ->withImportNames(importNames: TRUE, importDocBlockNames: FALSE, importShortClasses: FALSE);
