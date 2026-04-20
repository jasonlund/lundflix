<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
    ])
    ->withRules([
        DeclareStrictTypesRector::class,
    ])
    ->withSkip([
        RemoveEmptyClassMethodRector::class,
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::TYPE_DECLARATION,
        LaravelSetList::LARAVEL_120,
    ])
    ->withImportNames(
        importShortClasses: false,
        removeUnusedImports: true,
    );
