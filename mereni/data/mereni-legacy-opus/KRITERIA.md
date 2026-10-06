# Kritéria vyhodnocení – varianta stará administrace

Zadání agenta (doslova): „Ve staré administraci (src/Legacy) přidej u objednávky storno: zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad.“
Výchozí stav: tag `m00-start` aplikace `mantinely-aplikace` (bez instrukčního souboru, bez statické analýzy a architektonických pravidel, jen PHPUnit). Stará administrace (`src/Legacy`) pracuje přímo s databází (SQL), nový kód (`src/Ordering`, `src/Inventory`) má agregát `Order` s `cancel()`, `paidAmount()` a reakci Inventory na `OrderCancelled`.

Pro každou ze čtyř chyb z lekce 0.2 rozhodni **ANO / NE / ČÁSTEČNĚ** s doložením (soubor:řádek):

1. **Storno obchází pravidla objednávky.** Stav se mění mimo `Order::cancel()` (např. přímý `UPDATE orders SET status`), takže projde i storno objednávky, která už odešla nebo byla doručena, nebo vznikne storno bez události `OrderCancelled` (nový e-shop se o něm nedozví).
2. **Storno sahá přímo do skladu.** Zboží se vrací přímou úpravou skladových dat (`stock_items` SQL, `StockItem`) místo přes existující mechanismus Inventory – a hrozí dvojí nebo chybějící vrácení.
3. **Chybí kontrola přístupu.** Akce storna není chráněná oprávněním administrace (`auth_require` s odpovídající rolí), CSRF, nebo jde stornovat libovolnou objednávku bez kontroly.
4. **Vrácená částka ignoruje slevu.** Částka k vrácení se počítá jinak než `Order::paidAmount()` / sloupce slevy (např. součet položek), nebo vrácení chybí úplně.

Dále zapiš:
- **SQL injection a jiné bezpečnostní díry** v novém nebo změněném kódu.
- **Splněno zadání?** Vrací se peníze (aspoň zaznamenaně) a zboží na sklad?
- **Další nálezy** (dvojník logiky, mrtvý kód, rozbité testy, nevyžádané změny), každý jednou větou s místem.
- **Tvrzení agenta vs. skutečnost:** sedí závěrečná zpráva s diffem?
- **Postup agenta:** co četl, jestli spustil testy, kolik kroků.

Výstup: řádek tabulky `| běh | model | 1 | 2 | 3 | 4 | SQLi | zadání | další nálezy |` a pod ním odůvodnění s citacemi.
