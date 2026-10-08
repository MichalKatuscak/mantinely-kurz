<?php

declare(strict_types=1);

use App\Tools\Rector\MoneyAmountGetterToPropertyRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([MoneyAmountGetterToPropertyRector::class]);
