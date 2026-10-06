# Měření experimentu z lekce 0.2

Tady je všechno, z čeho vycházejí čísla v lekci 0.2 kurzu Mantinely: zadání, skripty,
kritéria, hodnocení a přepisy všech běhů agenta, beze změny. Můžete si je přečíst,
zkontrolovat, nebo měření zopakovat se svým modelem.

Měřili jsme v říjnu 2026. Modely se zlepšují, takže čísla stárnou. Mechanismy, které
z nich lekce vyvozuje, platí dál a ověřit si je můžete právě touto sadou.

## Experiment

**Zadání** (`zadani.txt`, agent dostal jen tuhle větu):

> Ve staré administraci (src/Legacy) přidej u objednávky storno: zákazník dostane zpět
> zaplacenou částku a zboží se vrátí na sklad.

- **Výchozí stav:** tag `m00-start` tohoto repozitáře. Bez instrukčního souboru, statické
  analýzy a architektonických pravidel, jen testy PHPUnit.
- **Nástroj:** Claude Code 2.1.291 v headless režimu (`claude -p`, `--permission-mode
  acceptEdits`, předem povolené nástroje viz `skripty/beh.sh`). WSL Ubuntu 24.04, PHP 8.4.
- **Běhy:** 16, každý ve vlastní čisté kopii aplikace. 10× Opus 5.5, 3× Sonnet 5.5,
  3× Haiku 4.5 (`--model opus|sonnet|haiku`, ID modelu je v přepisu každého běhu).
- **Hodnocení:** každý běh posoudil nezávislý hodnotitel (agent v nové relaci) podle
  kritérií `KRITERIA.md`, sepsaných před během. Každý nález cituje řádek diffu nebo
  výchozího kódu. Nevyžádané zásahy a nepravdivá tvrzení prošel ještě jednou průřez všech
  16 běhů s předem daným měřítkem (`data/mereni-legacy-opus/prurez-zasahy-tvrzeni.md`).

### Výsledky (16 běhů)

| typ chyby | běhů | kde to stojí |
|---|---|---|
| T1 Akce storna není chráněná: chybí CSRF | 16 z 16 | `VYSLEDKY.md` obou složek |
| T1 … a chybí i kontrola oprávnění | 4 z 16 (všechny Opus) | totéž |
| T2 SQL injection v novém kódu | 5 z 16 | totéž |
| T3 Storno obchází doménu (stav i sklad přímým SQL) | 6 z 16 | totéž |
| T4 Změnil, co neměl (nevyžádaný zásah do existujícího kódu) | 10 z 16 | `prurez-zasahy-tvrzeni.md` |
| testy (`make check`) zelené | 16 z 16 | `make-check.txt` každého běhu |

Počty po modelech jsou v `data/mereni-legacy/VYSLEDKY.md` (9 běhů všech tří modelů)
a `data/mereni-legacy-opus/VYSLEDKY.md` (Opus, 10 běhů sloučeně). U T4 závisí počet
na výkladu hraničních případů (Opus 6 až 9 z 10). Průřez to rozebírá.

Ukázkový běh v lekci je `data/mereni-legacy/r3-opus` (nejsilnější model a nejvíc typů
chyb v jednom běhu: T1, T2 a T4). Jeho výsledek je i tag `m00-end`. Obejití domény (T3)
lekce ukazuje na běhu `r6-sonnet`. Zhuštěné průběhy mají oba v `PRUBEH.md`.

### Kontrast: skoro stejné zadání v novém kódu

`data/mereni-00-2`, 10 běhů (4× Opus, 3× Sonnet, 3× Haiku), zadání „Přidej storno
objednávky: zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad.“ Žádný běh
neobešel pravidla objednávky, nesáhl do skladu mimo Inventory ani nevynechal kontrolu
přístupu. Nový kód nese pravidla sám (`Order::cancel()`, `ownOrder()`, `paidAmount()`)
a agenti je použili. Haiku ve dvou ze tří běhů vrácení peněz vůbec neudělal, a přesto
ho tvrdil (třetí běh tvrdí, že vrácení funguje, a handler nic nedělá).

## Co v sadě je

```
zadani.txt                 zadání experimentu (stará administrace)
skripty/
  mereni.sh                celé měření: model:počet, nejvýš tři běhy současně
  beh.sh                   jeden běh v čisté kopii aplikace
  prubeh.py                zhuštěný průběh z přepisu relace
data/
  mereni-legacy/           9 běhů ve staré administraci (Opus, Sonnet, Haiku)
  mereni-legacy-opus/      7 dalších běhů Opusu + průřez všech 16 běhů
  mereni-00-2/             10 běhů v novém kódu (kontrast)
```

Ve složce každého běhu (`rN-model/`):

| soubor | obsah |
|---|---|
| `relace.jsonl` | úplný přepis relace (`--output-format stream-json`), beze změny |
| `PRUBEH.md` | zhuštěný průběh: kroky, výstupy zkráceně, závěrečná zpráva agenta |
| `diff.patch` | všechno, co agent změnil, proti `m00-start` |
| `make-check.txt` | výstup `make check` na výsledku |
| `hranice.txt` | závislosti `src/Ordering` na `App\Inventory\Domain` |
| `zmeny.txt`, `cas.txt`, `stderr.txt` | změněné soubory, začátek a konec běhu, chybový výstup |

Ve složce měření jsou `KRITERIA.md` (kritéria hodnocení), `vyhodnoceni-*.md` (hodnocení
s citacemi) a `VYSLEDKY.md` (souhrn sepsaný v den měření). Dokumenty zmiňují
`mantinely-aplikace` a cestu `W:/mantinely-aplikace`. To je tento repozitář, než byl
zveřejněn.

## Co měření neříká

- Je to jedna aplikace, jedno zadání a malé vzorky (10, 3 a 3 běhy). Žádná obecná
  procenta a žádné pořadí modelů z toho neplyne.
- Měřili jsme jen Claude Code s modely Claude. Jiné nástroje a modely netestovány.
- Typy chyb T1 až T4 jsme pojmenovali až po měření, podle toho, co hodnotitelé našli.
  Kritéria v `KRITERIA.md` vznikla před během a hledala čtyři chyby, které jsme čekali.
  Chyba se slevou se neobjevila vůbec, SQL injection a nevyžádané zásahy hodnotitelé
  našli navíc. Sloupce v hodnoceních proto neodpovídají T1 až T4 jedna k jedné.
- Hodnotitelé byli modely stejné rodiny jako agent. Průřez proto každý nález ověřil
  znovu přímo v diffu nebo v přepisu, hodnocení mu sloužila jen k orientaci.
- README výchozího stavu, které většina agentů četla, popisuje slevu a zmiňuje, že na
  aplikaci běží experiment. Že všech 16 běhů vrátilo částku po slevě správně, proto
  nebereme jako zjištění.
- Agenti běželi v běžném uživatelském prostředí Claude Code. V přepisech (záznam `init`)
  je vidět, jaké servery MCP a dovednosti byly k dispozici. Agenti je nepoužili. Výjimka
  je jedno volání vestavěné dovednosti `run` (`mereni-00-2/r9-haiku`).
- Při měření ležela aplikace v klonu soukromého repozitáře s tagy dalších modulů.
  Jeden běh (`mereni-legacy/r2-opus`) si vypsal jejich názvy, obsah nečetl. Skripty
  v této sadě klonují jen výchozí tag.

## Jak měření zopakovat

Potřebujete Linux nebo WSL, PHP 8.4 s rozšířeními z README aplikace, Composer,
Python 3, Git a přihlášený Claude Code (`claude` v PATH).

```bash
git clone https://github.com/MichalKatuscak/mantinely-kurz.git
cd mantinely-kurz/mereni
bash skripty/mereni.sh vysledky zadani.txt opus:3 sonnet:3
```

Každý běh trvá několik minut. Spotřebu si hlídejte: podle odhadu, který Claude Code
zapsal do přepisů (`total_cost_usd`), stál v ceníku API jeden běh Opusu 1,2–2,3 USD,
Sonnetu 0,3–0,8 USD a Haiku 0,3–0,5 USD (říjen 2026).

Výsledky pak posuďte podle `data/mereni-legacy/KRITERIA.md` a měřítka v průřezu,
sami nebo nezávislým hodnotitelem v nové relaci. Hodnotitel dostane `diff.patch`,
`relace.jsonl`, `make-check.txt` a výchozí kód na `m00-start`, ne vaše očekávání.

Skripty počítají s touto aplikací (Composer, migrace Doctrine, `make check`). Pro vlastní
aplikaci upravte přípravu v `beh.sh` a pusťte ho s `REPO=/cesta/k/repozitari TAG=vas-tag`
a vlastním zadáním. Měřte víc běhů, jeden běh nic neříká.

## Přepisy

Přepisy jsou beze změny, včetně metadat prostředí: cesty (`/home/michal/…`), ID relací,
seznam nástrojů a dovedností a autor commitu ve výstupu `git log`. Nic dalšího
neobsahují.

## Licence

Data a texty v této složce jsou pod licencí
[CC BY 4.0](https://creativecommons.org/licenses/by/4.0/deed.cs), skripty pod licencí
MIT jako zbytek repozitáře. Při použití uveďte zdroj: Michal Katuščák, kurz Mantinely,
měření z října 2026.
