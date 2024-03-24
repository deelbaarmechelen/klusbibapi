<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src'
    ]);

    // register a single rule
    //$rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);

    // define sets of rules
    $rectorConfig->sets([
        //LevelSetList::UP_TO_PHP_56
        LevelSetList::UP_TO_PHP_70
        //LevelSetList::UP_TO_PHP_71
        //LevelSetList::UP_TO_PHP_72
        //LevelSetList::UP_TO_PHP_73
        //LevelSetList::UP_TO_PHP_74
        //LevelSetList::UP_TO_PHP_80
        //LevelSetList::UP_TO_PHP_81
        //LevelSetList::UP_TO_PHP_82
    ]);
};
