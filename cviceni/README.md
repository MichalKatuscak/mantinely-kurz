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
