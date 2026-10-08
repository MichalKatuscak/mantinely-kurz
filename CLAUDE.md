# Aplikace kurzu Mantinely

## Příkazy
- Všechny kontroly: `make check`
- Konzolové příkazy přes `bin/console`
- Stav kontejneru, rout a listenerů zjišťuj přes `bin/console debug:*`
- Migrace jen přes generátor migrací (`bin/console make:migration`), nikdy ruční úpravou schématu
- Bez zeptání žádný nový bundle ani balíček
- Po každém kroku plánu commit, před commitem `make check`

## Kód
- Kód je po ohraničených kontextech `src/Ordering`, `src/Inventory`, vrstvy Domain / Application / Infrastructure
- Ordering a Inventory spolu mluví jen přes doménové události
- Stav objednávky a skladu mění jen doména, i ze `src/Legacy` (command, ne SQL)
- Události v minulém čase bez přípony `Event`, command na command busu bez přípony `Command`
- Existující kód v `src/Ordering` a `src/Inventory` bez zadání neměnit
- `src/Legacy` bez zadání nerefaktorovat
- Nový případ užití: skill `novy-pripad-uziti`

## Slovník
| pojem | v kódu |
|---|---|
| storno | `cancel()`, `OrderCancelled` |
| sleva | `discount` |
| zaplacená částka | `Order::paidAmount()` |
