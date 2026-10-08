<?php

declare(strict_types=1);

use App\Tools\Rector\MoneyAmountGetterToPropertyRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/tools',
    ])
    ->withSkip([
        // Stará administrace se mění jen se zadáním (modul 10).
        __DIR__.'/src/Legacy',
        __DIR__.'/tests/PHPStan/data',
        __DIR__.'/tests/Rector/Fixture',
    ])
    ->withPhpSets()
    ->withAttributesSets(symfony: true, doctrine: true, phpunit: true)
    ->withRules([
        MoneyAmountGetterToPropertyRector::class,
    ]);
