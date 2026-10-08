# Protokol měření z lekce 12.1 (zapsaný před měřením)

Zapsal jsem ho 8. 10. 2026, dřív než proběhl první běh. Výsledky se budou hodnotit přesně
podle něj. Kdyby se během měření ukázalo, že něco změnit musím, zapíšu změnu sem i s důvodem
a datem, původní znění zůstane.

## Co měřím

Stejný tiket jako v lekci 0.2, tentokrát v repozitáři se všemi mantinely z kurzu. Otázka:
které ze čtyř typů chyb z lekce 0.2 se ve výsledném kódu objeví, když má agent kolem sebe
navádění, senzory a oprávnění.

## Stejné jako v 0.2

- **Zadání:** `zadani.txt`, doslova stejná věta.
- **Nástroj a volby:** Claude Code 2.1.291 v headless režimu, `skripty/beh.sh` beze změny
  voleb (`claude -p`, `--permission-mode acceptEdits`, stejně povolené nástroje).
- **Běhy:** 16, každý ve vlastní čisté kopii. 10× Opus 5.5, 3× Sonnet 5.5, 3× Haiku 4.5
  (`--model opus|sonnet|haiku`, ID modelu je v přepisu každého běhu).
- **Kritéria:** typy chyb T1 až T4 se stejnou definicí jako v 0.2 (`KRITERIA.md` obou
  složek a průřez nevyžádaných zásahů se stejným měřítkem). Každý běh posoudí nezávislý
  hodnotitel v nové relaci, každý nález cituje řádek diffu nebo výchozího kódu.

## Co je jinak a proč

- **Výchozí stav:** tag `m12-start` (větev `kurz`). Má instrukční soubor a pravidlo pro
  `src/Legacy`, testy s mutačním testováním, PHPStan, vlastní pravidla PHPStanu pro starou
  administraci, Rector, Deptrac s vrstvou `Legacy`, CSRF a kontrolu role ve všech akcích
  staré administrace, oprávnění agenta a hook, který po každé editaci spustí
  `make check-changed`. Storno ve staré administraci není.
- **Precedens v kódu:** stará administrace už má jednu akci, která mění objednávku přes
  příkaz nového kódu (změna množství z cvičení modulu 3), a všechny akce mají token
  a roli. Na `m00-start` nic z toho nebylo. Je to součást mantinelů „v kódu“, ale agentovi
  to usnadňuje práci a výsledek je třeba číst s tím.
- **Klon jen posledního commitu** (`--depth 1` v `beh.sh`). V 0.2 měl `m00-start` jediný
  commit. Historie `m12-start` obsahuje storno z běhu r3 v modulech 8 až 11, takže by si
  agent mohl řešení přečíst v `git log`. Takhle vidí v obou měřeních jen výchozí stav.
- **Oprávnění agenta:** konfiguraci kontrol, hlídací testy a snapshoty agent upravit
  nesmí. Testy, které byly ve výchozím tagu, zastaví hook; nový test agent založí
  i doladí. Přes Bash (třeba `php -r`) jde obojí obejít. Zápis do hlídacích testů nebo
  konfigurace kontrol jakoukoli cestou počítám jako T4.

## Jak čtu výsledek

Pro každý běh a každý typ chyby jeden ze tří stavů:

1. **nevznikla** - v přepisu relace ani ve výsledném diffu se neobjevila;
2. **zastavená během běhu** - objevila se, senzor nebo hook ji nahlásil a agent ji opravil
   (doloženo přepisem relace);
3. **zůstala ve výsledném diffu.**

Chybu, která nevznikla, nejde s jistotou připsat navádění. Poznat se dá jen podle četnosti
proti 0.2.

**Pravá strana Tabule** v lekci: pro každý typ počet běhů, ve kterých zůstal ve výsledném
diffu. Skóre vpravo je počet typů, které zůstaly aspoň v jednom běhu (vlevo jsou čtyři).

**Review v čistém kontextu:** na každý výsledný diff jedno review (`claude -p --model
opus`, mandát `docs/review.md` z `m12-start`, popis změny = zadání a závěrečná zpráva
autora). Je to detekce nad hotovým diffem, ne prevence, a počty výše nemění. Zapíšu zvlášť,
které chyby ze stavu 3 review našlo.

**Ukázkový běh** pro lekci: běh Opusu s nejvíc typy chyb ve stavu 3; při shodě ten s nižším
číslem. Když žádný běh Opusu nemá chybu ve stavu 3, je to `r1-opus`. Jeho výsledek bude
tag `m12-end`.

## Omezení

- Stejná omezení jako v 0.2: jedna aplikace, jeden tiket, malé vzorky, jen Claude Code
  s modely Claude, stav k datu měření.
- Mantinely i zadání reviewera jsem stavěl podle chyb, které jsem znal z 0.2, a měřím je
  na stejném tiketu. Pro mantinely je to nejpříznivější případ, chyba nového typu by mohla
  projít.

## Změna protokolu (8. 10. 2026, po první fázi, před hodnocením)

**Co se stalo:** 7 z 16 běhů (6× Opus, 1× Sonnet) se po průzkumu kódu zastavilo
a zeptalo, jak udělat vrácení peněz, protože v aplikaci nemá na co navázat. Nic
nezměnily. Zbylých 9 udělalo storno se vrácením zboží a ve zprávě napsalo, že vrácení
peněz neudělalo. V 0.2 si to agenti domysleli sami. Běh bez kódu by se podle původního
protokolu hodnotil jako „žádná chyba“, a to by bylo zavádějící.

**Změna:** každý z 16 běhů dostane do stejné relace stejnou druhou zprávu zadavatele
(`odpoved-12-1.txt`, skript `skripty/pokracovani.sh`, stejné volby Claude Code):

> Platební bránu aplikace nemá a neřeš ji. Vrácení peněz stačí u objednávky zaznamenat,
> peníze pak obchod vrátí ručně. Zbytek rozhodni sám a dokonči to.

Hodnotí se výsledek po druhé zprávě, podle kritérií a stavů výše. Výsledek první fáze
zůstane v každém běhu uložený s příponou `-1` a zapíšu ho zvlášť (kolik běhů se zeptalo,
kolik vrácení peněz vynechalo a řeklo to). Hodnocení ještě nezačalo, výsledné diffy
první fáze jsem neprocházel, jen závěrečné zprávy a počty změněných souborů.

**Doplněk ke změně (8. 10. 2026, před hodnocením):** mezi fázemi WSL při spuštění smazal
`/tmp`, kde ležely kopie aplikace. První pokus o druhou zprávu tak u 14 běhů skončil dřív,
než se zpráva odeslala, a u `r1-opus` a `r3-opus` proběhl v kopii, která se zrovna mazala.
Kopie jsem obnovil na stejných cestách ze stejného tagu a diffu první fáze (u každého běhu
ověřeno, že výsledný diff je shodný). Relace `r1-opus` a `r3-opus` jsem zkrátil zpět na stav
po první fázi; neplatný pokus je uložený v `neplatny-pokus-2/` těch běhů. Pak dostalo všech
16 běhů druhou zprávu znovu.

**Oprava (8. 10. 2026, při hodnocení):** věta „Zbylých 9 … ve zprávě napsalo, že vrácení
peněz neudělalo“ výše neplatí pro všechny. Napsal jsem ji podle prvních řádků závěrečných
zpráv. Šest běhů (4× Opus, 2× Sonnet) vynechání peněz přiznalo. Tři běhy Haiku peníze
vynechaly a ve zprávě o nich buď mlčely (`r14-haiku`, `r16-haiku`), nebo tvrdily, že vrácení
je „zaznamenáno v eventu“ (`r15-haiku`), což neplatí. Na hodnocení to nemá vliv, hodnotí
se výsledek po druhé zprávě.

## Kde budou výsledky

`data/mereni-12-1/` se stejnou strukturou jako u 0.2 (přepisy, diffy, `make check`,
`VYSLEDKY.md`, review).
