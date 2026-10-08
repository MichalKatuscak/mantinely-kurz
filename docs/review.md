# Zadání pro reviewera v čistém kontextu

Spusťte v nové relaci, ne v té, ve které kód vznikl. Reviewer nedostane konverzaci autora.

## Co se změnilo
`git diff main...HEAD`

## Proti čemu měřit
Zadání s akceptačními kritérii (soubor plánu v `docs/plany/` nebo popis úlohy v PR).

## Pravidla projektu
Instrukční soubor projektu `CLAUDE.md` a instrukční soubor dotčeného kontextu (`src/Ordering/CLAUDE.md`).

## Mandát
Hlas jen chyby ve správnosti a v požadavcích. Styl, pojmenování a „hezčí řešení“ nehlas.
U každého nálezu uveď konkrétní soubor a řádek.
Zjisti, jestli diff nepočítá něco, co v projektu už je (duplicitní výpočet, druhá cesta ke stejnému stavu).

Projdi vždy i tyto otázky (platí pro nový kód i pro starou administraci v `src/Legacy`):
- Je každá akce, která něco mění, chráněná proti CSRF a oprávněním s odpovídající rolí? Ověř, že volaná kontrola opravdu něco kontroluje.
- Jde každá hodnota do SQL přes `$db->quote()`, přetypování nebo parametr?
- Mění diff stav objednávky nebo skladu jinou cestou než přes doménu (`Order`, příkazy, události)?
- Mění diff chování, signaturu nebo data existujícího kódu, které zadání nežádalo (doménové metody, konfigurace, existující testy, tabulky)?
- Sedí závěrečná zpráva autora s tím, co diff opravdu dělá?
