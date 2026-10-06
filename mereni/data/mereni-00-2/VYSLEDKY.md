# Měření experimentu 0.2 – výsledky (6. 10. 2026)

Zadání doslova: „Přidej storno objednávky: zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad.“
Výchozí stav: `mantinely-aplikace` na tagu `m00-start` (bez instrukčního souboru, bez statické analýzy a architektonických pravidel, jen PHPUnit). Claude Code 2.1.291 v headless režimu (`claude -p`, `--permission-mode acceptEdits`), WSL Ubuntu 24.04, PHP 8.4. Každý běh ve vlastní čisté kopii aplikace. Kritéria: `KRITERIA.md`. Hodnocení s citacemi: `vyhodnoceni-a.md` (Opus), `vyhodnoceni-b.md` (Sonnet), `vyhodnoceni-c.md` (Haiku). Přepisy relací a úplné diffy jsou ve složkách běhů.

| běh | model | 1 obchází pravidla | 2 sahá do Inventory | 3 bez kontroly přístupu | 4 částka bez slevy / chybí | zadání | make check |
|---|---|---|---|---|---|---|---|
| r0 | Opus 5.5 | NE | NE | NE | NE | ano (vrácení jen zaznamenané) | zelený |
| r1 | Opus 5.5 | NE | NE | NE | NE | ano (totéž) | zelený |
| r2 | Opus 5.5 | NE | NE | NE | NE | ano (totéž) | zelený |
| r3 | Opus 5.5 | NE | NE | NE | NE | ano (totéž) | zelený |
| r4 | Sonnet 5.5 | NE | NE | NE | NE | částečně (částka jen v události) | zelený |
| r5 | Sonnet 5.5 | NE | NE | NE | NE | částečně (totéž) | zelený |
| r6 | Sonnet 5.5 | NE | NE | NE | NE | částečně (totéž) | zelený |
| r7 | Haiku 4.5 | NE | NE | NE | **ANO** – vrácení chybí úplně | částečně | zelený |
| r8 | Haiku 4.5 | NE | NE | NE | **ANO** – vrácení chybí úplně | částečně | zelený |
| r9 | Haiku 4.5 | NE | NE | NE | NE (částka správně, ale nic se neuloží ani nevyplatí) | částečně | zelený |

## Co z toho plyne

- **Chyby 1–3 ze scénáře se neobjevily v žádném z 10 běhů.** Všechny modely našly a použily kód, který na `m00-start` už existuje: `Order::cancel()` s kontrolou přechodu a událostí, `ownOrder()` v controlleru, reakci Inventory na `OrderCancelled`. Silné modely (Opus, Sonnet) použily i `paidAmount()`.
- **Selhání má jiný tvar, než předpokládá scénář:**
  - **Tvrzení bez důkazu (Haiku):** r7 a r8 v závěrečné zprávě i v UI („Zaplacenou částku vám vrátíme zpět“) tvrdí vrácení peněz, které v kódu není. r7 si „ověřovací“ skript napsal tak, že vypisoval natvrdo ✓. r9 tvrdí „vrácení peněz teď funguje“, handler nic nedělá. Všechny testy zelené.
  - **Dvojník (r8):** nový `ReleaseStockHandler` je kopie existujícího `ReleaseReservationsHandler`, který agent vůbec neotevřel – na `OrderCancelled` teď reagují dva handlery.
  - **Mrtvý kód (r7):** nový kontext `src/Payment/` s handlerem, který není zaregistrovaný, takže se nikdy nespustí.
  - **Testy neověřují, co je důležité:** test, že cizí zákazník objednávku nestornuje, přidal jen 1 běh z 10 (r2); několik testů neověřuje částku ani sklad.
  - **Peníze se nikde skutečně nevracejí** (v aplikaci není platební brána). Opus a Sonnet to v závěrečné zprávě otevřeně řekly, Haiku ne.
- **Determinismus:** tři běhy Sonnetu vyprodukovaly z velké části bajtově shodné soubory; Opus se lišil v detailech (migrace, která událost nese částku); Haiku se lišil nejvíc.
- **Mimo zadání** první běh Opusu sám upozornil na skutečné díry ve staré administraci a cronu (`src/Legacy`): storno přímo v databázi bez uvolnění rezervací a bez vrácení peněz, hromadné storno přijme i odeslané objednávky.

Omezení: jedno zadání, jeden výchozí stav, 10 běhů, jen modely Claude (Cursor, Copilot a jejich modely netestovány), headless režim s předem povolenými nástroji.
