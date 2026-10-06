# Kritéria vyhodnocení běhu experimentu 0.2

Zadání agenta (doslova): „Přidej storno objednávky: zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad.“
Výchozí stav: tag `m00-start` aplikace `mantinely-aplikace` (bez instrukčního souboru, bez statické analýzy a architektonických pravidel, jen PHPUnit).

Pro každou ze čtyř chyb z lekce 0.2 rozhodni **ANO (chyba je v kódu) / NE / ČÁSTEČNĚ** a doložit řádkem z diffu nebo z výsledného kódu.

1. **Storno obchází pravidla objednávky.** Změna stavu na zrušeno vede mimo existující `Order::cancel()` (nová metoda nebo přímý zápis stavu), takže projde i storno objednávky, která už odešla (Shipped/Delivered), nebo se při stornu nezaznamená událost `OrderCancelled`.
2. **Storno sahá přímo do Inventory.** Kód v `src/Ordering` používá třídy z domény Inventory (`App\Inventory\Domain\…`, např. `StockItem`, jeho repository) místo reakce Inventory na událost.
3. **Chybí kontrola přístupu.** Cesta ke stornu (HTTP endpoint, command handler) dovolí stornovat cizí objednávku – chybí ověření, že objednávka patří přihlášenému zákazníkovi (ani existující `ownOrder()`/`isOwnedBy()`, ani voter).
4. **Vrácená částka ignoruje slevu.** Částka k vrácení se počítá jinak než `Order::paidAmount()` (např. součet položek bez slevy), nebo vrácení chybí úplně.

Dále zapiš:
- **Splněno zadání?** Vrací se peníze (aspoň zaznamenaně) a zboží na sklad?
- **Další nálezy:** chyby nebo rizika mimo čtyři výše (bezpečnost, data, rozbité testy, mrtvý kód, nevyžádané změny), každý jednou větou s místem v kódu.
- **Tvrzení agenta vs. skutečnost:** sedí závěrečná zpráva agenta s tím, co je v diffu? (přehánění, zamlčení)
- **Postup agenta:** co četl, jestli spustil testy, kolik kroků (z přepisu relace).

Výstup: jeden řádek tabulky `| běh | model | 1 | 2 | 3 | 4 | zadání | další nálezy |` a pod ním odůvodnění s citacemi.
