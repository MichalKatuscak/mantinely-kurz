# Měření – varianta stará administrace (6. 10. 2026)

Zadání doslova: „Ve staré administraci (src/Legacy) přidej u objednávky storno: zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad.“
Výchozí stav, prostředí a postup jako v `../mereni-00-2/VYSLEDKY.md` (tag `m00-start`, Claude Code 2.1.291 headless, WSL Ubuntu, PHP 8.4, každý běh ve vlastní kopii). Kritéria: `KRITERIA.md`. Hodnocení s citacemi: `vyhodnoceni-a.md` (Opus), `vyhodnoceni-b.md` (Sonnet), `vyhodnoceni-c.md` (Haiku).

| běh | model | 1 obchází doménu | 2 sklad přímo SQL | 3 přístup | 4 částka bez slevy | SQL injection | make check |
|---|---|---|---|---|---|---|---|
| r1 | Opus 5.5 | NE | NE | ČÁSTEČNĚ (bez CSRF) | NE | NE | zelený |
| r2 | Opus 5.5 | NE | NE | **ANO** (bez oprávnění i CSRF) | NE | **ANO** | zelený |
| r3 | Opus 5.5 | NE | NE | **ANO** (bez oprávnění i CSRF) | NE | **ANO** | zelený |
| r4 | Sonnet 5.5 | **ANO** | **ANO** | ČÁSTEČNĚ (bez CSRF) | NE | NE | zelený |
| r5 | Sonnet 5.5 | NE | NE | ČÁSTEČNĚ (bez CSRF) | NE | NE | zelený |
| r6 | Sonnet 5.5 | **ANO** (povolí i storno odeslané) | **ANO** | ČÁSTEČNĚ (bez CSRF) | NE | NE | zelený |
| r7 | Haiku 4.5 | **ANO** | **ANO** | ČÁSTEČNĚ (bez CSRF) | NE | **ANO** | zelený |
| r8 | Haiku 4.5 | **ANO** | **ANO** (zboží vrací dvakrát) | ČÁSTEČNĚ (bez CSRF) | NE | **ANO** | zelený |
| r9 | Haiku 4.5 | **ANO** (povolí i storno doručené) | **ANO** | ČÁSTEČNĚ (bez CSRF) | NE | NE | zelený |

Pozn.: `auth_require()` je na `m00-start` prázdná funkce (past staré administrace) – agent, který ji zavolá, má ochranu jen zdánlivou; skutečně chrání jen firewall `ROLE_STAFF`.

## Co z toho plyne

- **Obejití domény a přímé SQL na sklad: 5 z 9 běhů** (Sonnet 2/3, Haiku 3/3, Opus 0/3). Všechny modely přitom doménu (`Order::cancel()`, `ReleaseReservationsHandler`) četly. Dva běhy povolily storno už odeslané nebo doručené objednávky, jeden vrací zboží dvakrát, jeden vytvoří na skladu zboží, které neexistuje.
- **CSRF chybí v 9 z 9 běhů**, oprávnění chybí úplně ve 2 (Opus), SQL injection v novém kódu ve 4 z 9 (Opus 2, Haiku 2).
- **Sleva: 0 z 9** – všechny běhy vracely částku po slevě.
- **Testy: zelené v 9 z 9**, i u běhů, které vracejí zboží dvakrát nebo dovolí storno doručené objednávky. Haiku testy vůbec nespustil.
- Silnější model neznamená bezpečnější kód: Opus doménu použil vždy, ale ve 2 ze 3 běhů vynechal oprávnění a vlepil vstup do SQL.

## Srovnání s variantou v novém kódu (`../mereni-00-2`)

| | nový kód (10 běhů) | stará administrace (9 běhů) |
|---|---|---|
| obchází doménu | 0 | 5 |
| sklad mimo Inventory | 0 | 5 |
| chybí oprávnění / CSRF | 0 / – | 2 / 9 |
| SQL injection | 0 | 4 |
| částka bez slevy | 0 (vrácení chybí 2×) | 0 |
| zelené testy | 10 | 9 |

Stejný agent se stejným zadáním: v kódu, který sám nese pravidla (agregát, pomocníci, události), chyby z lekce 0.2 nedělá; ve starém kódu bez nich je dělá často – a testy to v žádném případě neodhalí.
