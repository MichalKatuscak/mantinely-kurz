<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\BoolConfig;
use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

/*
 * Vrstvy po ohraničených kontextech, ne podle technické role jako v knize.
 * Kniha dovolí vrstvě Application celou vrstvu Domain, takže handler z Orderingu
 * by mohl sáhnout na StockItem z Inventory. Tady to Deptrac zakáže.
 *
 * Stará administrace (src/Legacy) smí do nového kódu jen příkazem Orderingu,
 * s ID a výjimkami, které příkaz hází. Žádná vrstva nového kódu nesmí na ni,
 * kromě protikorupční vrstvy LegacyAcl (src/<kontext>/Infrastructure/Legacy).
 *
 * Spouští se s --fail-on-uncovered: třída, která neleží v žádné vrstvě, je chyba.
 */
return static function (DeptracConfig $config): void {
    if (file_exists(__DIR__.'/deptrac.baseline.yaml')) {
        $config->baseline('deptrac.baseline.yaml');
    }

    // Pozor: Deptrac vzory tříd ještě jednou escapuje, proto v řetězci stačí
    // jedno zpětné lomítko ('\\'), aby ve výsledném regexu znamenalo lomítko.

    // Přímý přístup k databázi. Sbírá se podle jména třídy, ne celý balíček
    // doctrine/orm, aby do vrstvy nespadly mapovací atributy v doméně.
    $persistenceClasses = [
        ClassLikeConfig::create('^Doctrine\\DBAL\\Connection$'),
        ClassLikeConfig::create('^Doctrine\\ORM\\EntityManagerInterface$'),
        ClassLikeConfig::create('^Doctrine\\ORM\\QueryBuilder$'),
    ];

    $config
        ->paths('./src')
        ->layers(
            // Události, výjimky a identifikátory mají vlastní vrstvy, proto je negativní
            // lookahead vynechá z doménové vrstvy: žádná třída ve dvou vrstvách.
            $orderingDomain = Layer::withName('OrderingDomain')->collectors(
                DirectoryConfig::create('src/Ordering/Domain/(?!Event/|Exception/|ValueObject/[^/]+Id[.]php).*'),
            ),
            $orderingEvents = Layer::withName('OrderingEvents')->collectors(
                DirectoryConfig::create('src/Ordering/Domain/Event/.*'),
            ),
            // Výjimky Orderingu mají vlastní vrstvu kvůli staré administraci:
            // chytá výjimky, které příkaz hází, ale do domény Orderingu nesmí.
            $orderingErrors = Layer::withName('OrderingErrors')->collectors(
                DirectoryConfig::create('src/Ordering/Domain/Exception/.*'),
            ),
            $orderingApplication = Layer::withName('OrderingApplication')->collectors(
                DirectoryConfig::create('src/Ordering/Application/.*'),
            ),
            $orderingInfrastructure = Layer::withName('OrderingInfrastructure')->collectors(
                DirectoryConfig::create('src/Ordering/Infrastructure/(?!Legacy/).*'),
            ),
            // Protikorupční vrstva: jediné místo nového kódu, které smí na src/Legacy.
            $legacyAcl = Layer::withName('LegacyAcl')->collectors(
                DirectoryConfig::create('src/[^/]+/Infrastructure/Legacy/.*'),
            ),
            $inventoryDomain = Layer::withName('InventoryDomain')->collectors(
                DirectoryConfig::create('src/Inventory/Domain/(?!ValueObject/[^/]+Id[.]php).*'),
            ),
            $inventoryApplication = Layer::withName('InventoryApplication')->collectors(
                DirectoryConfig::create('src/Inventory/Application/.*'),
            ),
            $inventoryInfrastructure = Layer::withName('InventoryInfrastructure')->collectors(
                DirectoryConfig::create('src/Inventory/Infrastructure/.*'),
            ),
            // ID přecházejí hranice kontextů (Inventory pracuje s OrderId a ProductId).
            $identifiers = Layer::withName('Identifiers')->collectors(
                ClassLikeConfig::create('^App\\[^\\]+\\Domain\\ValueObject\\[^\\]+Id$'),
            ),
            $sharedKernel = Layer::withName('SharedKernel')->collectors(
                DirectoryConfig::create('src/SharedKernel/.*'),
            ),
            $identity = Layer::withName('Identity')->collectors(
                DirectoryConfig::create('src/Identity/.*'),
            ),
            // Stará administrace. Nový kód na ni nesmí, jen protikorupční vrstva LegacyAcl.
            $legacy = Layer::withName('Legacy')->collectors(
                DirectoryConfig::create('src/Legacy/.*'),
            ),
            $kernel = Layer::withName('Kernel')->collectors(
                DirectoryConfig::create('src/Kernel[.]php'),
            ),
            $persistence = Layer::withName('Persistence')->collectors(...$persistenceClasses),
            // Framework a knihovny, kromě tříd z vrstvy Persistence.
            $vendor = Layer::withName('Vendor')->collectors(
                BoolConfig::create()
                    ->must(ClassLikeConfig::create('^(Symfony|Doctrine|Psr|Twig)\\'))
                    ->mustNot(...$persistenceClasses),
            ),
        )
        ->rulesets(
            Ruleset::forLayer($orderingDomain)->accesses($orderingEvents, $orderingErrors, $identifiers, $sharedKernel, $vendor),
            Ruleset::forLayer($orderingEvents)->accesses($identifiers, $sharedKernel),
            Ruleset::forLayer($orderingErrors)->accesses($identifiers, $sharedKernel),
            Ruleset::forLayer($orderingApplication)->accesses($orderingDomain, $orderingEvents, $orderingErrors, $identifiers, $sharedKernel, $vendor),
            Ruleset::forLayer($orderingInfrastructure)->accesses(
                $orderingApplication, $orderingDomain, $orderingEvents, $orderingErrors, $identifiers, $sharedKernel, $identity, $persistence, $vendor,
            ),
            // Inventory zná z Orderingu jen události a ID, nikdy model objednávky.
            Ruleset::forLayer($inventoryDomain)->accesses($identifiers, $sharedKernel, $vendor),
            Ruleset::forLayer($inventoryApplication)->accesses($inventoryDomain, $orderingEvents, $identifiers, $sharedKernel, $vendor),
            Ruleset::forLayer($inventoryInfrastructure)->accesses($inventoryDomain, $inventoryApplication, $identifiers, $sharedKernel, $persistence, $vendor),
            Ruleset::forLayer($identifiers)->accesses($vendor),
            Ruleset::forLayer($sharedKernel)->accesses($vendor),
            Ruleset::forLayer($identity)->accesses($identifiers, $vendor),
            // Protikorupční vrstva: jediná z nového kódu smí na starou administraci.
            Ruleset::forLayer($legacyAcl)->accesses($orderingApplication, $orderingDomain, $identifiers, $sharedKernel, $legacy, $persistence, $vendor),
            // Stará administrace jen příkazem: aplikační vrstva Orderingu, výjimky,
            // které příkaz hází, a ID. Doménu Orderingu ani Inventory přímo ne.
            Ruleset::forLayer($legacy)->accesses($orderingApplication, $orderingErrors, $identifiers, $vendor),
            Ruleset::forLayer($kernel)->accesses($vendor),
            Ruleset::forLayer($persistence),
            Ruleset::forLayer($vendor),
        );
};
