# Podklady ke cvičením

Výsledky dvou běhů agenta z experimentu v lekci 0.2 jako patche. Na vlastní větvi je
nasadíte přes `git apply` a vyzkoušíte na nich svoje pravidla PHPStanu (modul 6) nebo
Deptrac (modul 7). Zpátky: `git checkout -- . && git clean -fd src tests`.

- `r3-opus.patch` - běh r3 (tag `m00-end`): storno ve staré administraci přes příkaz
  `CancelOrder`, ID objednávky z adresy vlepené do SQL. Přenesený na `m06-start` a bez
  mostu ze staré administrace na command bus (`legacy_command()`, `LegacyFrontController`,
  `config/services.yaml`). Ten si běh r3 postavil sám a v aplikaci je ve stejném tvaru
  od cvičení 3 (`m03-end`).
- `r6-sonnet.patch` - běh r6 beze změny obsahu: storno, které obešlo doménu přímým
  zápisem do `orders` a `stock_items` (`src/Legacy/lib/OrderCancellation.php`,
  `StockReport::releaseReservations()`). Proti diffu běhu se liší jen hlavičky (čísla
  řádků, hashe), přidané a odebrané řádky jsou stejné.

## Modul 7: čtyři porušení hranic

Každé porušení je samostatný patch. Nasazuje se přes `git apply`, vrací přes `git apply -R`.

| Patch | Porušení |
|---|---|
| `m07/01-handler-vola-stockitem.patch` | handler storna z Orderingu načte `StockItem` z Inventory a uvolní rezervaci sám |
| `m07/02-update-pres-dbal.patch` | aplikační vrstva pošle `UPDATE orders` přes `Doctrine\DBAL\Connection`; pravidla PHPStanu pro starou administraci ho nevidí, hlídají jen `src/Legacy` |
| `m07/03-trida-mimo-vrstvy.patch` | nová třída `src/Ordering/Util/MoneyHelper.php` v adresáři, který žádná vrstva nezná |
| `m07/04-legacy-vola-sklad.patch` | stará administrace si vezme repozitář skladu (`StockItemRepository`) a rezervace objednávky uvolní sama, bez příkazu a bez SQL |

Na všech čtyřech patchích projdou testy i PHPStan. `make check` s Deptracem
(`--fail-on-uncovered`) má selhat na každém z nich.

## Modul 10: report měsíčních tržeb

`tests/Legacy/fixtures/report-months.sql` naplní testovací databázi pro charakterizační
testy: měsíc bez objednávek (2025-11), se slevou (2025-10), se stornem (2026-02)
a přelom roku (2025-12 a 2026-01). Report se volá přes
`App\Legacy\Admin\ReportController::monthlyAction()` s `$_GET['month']`, databázi mu
nastavíte přes `$GLOBALS['LEGACY_DSN']` (viz `src/Legacy/lib/db.php`).
