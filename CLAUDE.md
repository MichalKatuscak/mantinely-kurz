# Aplikace kurzu Mantinely

## Příkazy
- Všechny kontroly: `make check`
- Konzolové příkazy přes `bin/console`
- Stav kontejneru, rout a listenerů zjišťuj přes `bin/console debug:*`; zapojení služeb ověř přes `debug:autowiring` a `debug:container`, nehádej z konfigurace
- Migrace jen přes generátor migrací (`bin/console make:migration`), nikdy ruční úpravou schématu
- Bez zeptání žádný nový bundle ani balíček
- Po každém kroku plánu commit, před commitem `make check`

## Kontroly
- Po každé změně: `make test-domain` (doménové testy bez jádra, pár sekund)
- Po každé editaci spouští hook `make check-changed` (PHPStan na změněné soubory, ve staré administraci `make phpstan-legacy`, k tomu Deptrac). Nahlášenou chybu oprav, baseline negeneruj znovu.
- Před commitem: `make check`
- Ve fázi implementace testy neměň. Když test odporuje zadání, zastav se a řekni to.
- Ve zprávě o hotové práci tvrď jen to, co ověřil test nebo příkaz; co ověřené není, napiš výslovně.

## Kód
- Kód je po ohraničených kontextech `src/Ordering`, `src/Inventory`, vrstvy Domain / Application / Infrastructure
- Ordering a Inventory spolu mluví jen přes doménové události
- Stav objednávky a skladu mění jen doména, i ze `src/Legacy` (command, ne SQL)
- Každá hodnota v SQL přes `$db->quote()` nebo přetypování (`(int)`), nikdy vlepená proměnná
- Události v minulém čase bez přípony `Event`, command na command busu bez přípony `Command`
- Částky drž v haléřích jako int (`Money::$amountInCents`), měnu ber z enumu `Currency`
- Bez výslovného zadání neměň chování ani signatury existujících metod (hlavně doménových: `Order`, `StockItem`), konfiguraci (`config/`, `*.neon`, `Makefile`) ani existující testy. Když bez toho úkol nejde, zastav se a řekni to.
- Malé změny: jen to, co zadání žádá, bez úklidu a přestavby okolního kódu
- `src/Legacy` bez zadání nerefaktorovat
- Nový případ užití: skill `novy-pripad-uziti`

## Slovník
| pojem | v kódu |
|---|---|
| storno | `cancel()`, `OrderCancelled` |
| sleva | `discount` |
| zaplacená částka | `Order::paidAmount()` |
