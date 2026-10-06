# Vyhodnocení C – běhy r7, r8, r9 (Haiku), varianta stará administrace

Zadání: „Ve staré administraci (src/Legacy) přidej u objednávky storno: zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad.“ Výchozí stav: tag `m00-start`. Kritéria: `KRITERIA.md`.

Čísla řádků odkazují na **výsledný soubor** (m00-start + `diff.patch` daného běhu, aplikováno v pomocné kopii mimo repozitáře). `m00-start:` označuje původní kód. Model ve všech třech relacích: `claude-haiku-4-5-20251001`.

| běh | model | 1 | 2 | 3 | 4 | SQLi | zadání | další nálezy |
|---|---|---|---|---|---|---|---|---|
| r7-haiku | Haiku 4.5 | ANO | ANO | ČÁSTEČNĚ | NE | ANO | ANO s výhradami | storno přes výběr stavu v `order_edit` dál nic nevrací, a přitom smazal TODO, které na to upozorňovalo; opakované storno zapíše refundaci znovu; refundace se zapíše i u nezaplacené objednávky; testy nespustil |
| r8-haiku | Haiku 4.5 | ANO | ANO (zboží vrací dvakrát) | ČÁSTEČNĚ | NE | ANO (i nový endpoint) | ČÁSTEČNĚ | `on_hand += množství` k uvolnění rezervace, takže na skladu přibude zboží, které neexistuje; detail ukazuje „vrácenou částku“ u každé stornované objednávky; refundace i u nezaplacené; zkoušel commitnout; testy nespustil |
| r9-haiku | Haiku 4.5 | ANO (i doručenou) | ANO | ČÁSTEČNĚ | NE | NE (v nových dotazech) | ANO s výhradami | prázdné rezervace ukládá jako `''`, na čemž nový e-shop při další rezervaci produktu spadne (odvozeno z kódu); hromadné storno refunduje už stornované objednávky znovu; refundace i u doručené a nezaplacené objednávky; nevyžádaná úprava `cron.php`; testy nespustil (jen `php -l`) |

Společný kontext k bodu 3: `auth_require()` je od roku 2024 vypnutá a vždy vrací `true` (`m00-start:src/Legacy/lib/auth.php:76-77`). CSRF ochrana ve staré administraci neexistuje vůbec (v `src/Legacy` se nevyskytuje `csrf` ani `token`). Skutečnou ochranu dává jen Symfony firewall `^/admin` → `ROLE_STAFF` (`m00-start:config/packages/security.yaml`). Žádný ze tří běhů to nezjistil ani neřešil: role se „kontroluje“ jen formálně, voláním no-op funkce, a CSRF chybí. Proto všude ČÁSTEČNĚ.

Společný kontext k bodu 4: všechny tři běhy počítají částku jako součet položek minus `discount_amount_in_cents` (oříznuto na 0). Odpovídá to `Order::paidAmount()` (`m00-start:src/Ordering/Domain/Model/Order.php:214-218`) i legacy `order_total_after_discount()`, takže chyba „ignoruje slevu“ nenastala. Peníze se ale nikde skutečně nevracejí, jen se zapíše záznam (audit log, v r9 navíc poznámka). Žádný běh nekontroluje, jestli objednávka byla zaplacená, takže se „vrácení“ zapíše i u konceptu nebo potvrzené nezaplacené objednávky.

Pro férovost: na `m00-start` není v Ordering žádný příkaz ani handler pro storno (`CancelOrder` neexistuje) a `src/Legacy` není Symfony služba (`config/services.yaml` registruje jen Ordering, Inventory, Identity a SharedKernel). Použít `Order::cancel()` by tedy vyžadovalo vlastní napojení přes `LegacyFrontController`. `hranice.txt` (závislost Ordering → Inventory\Domain) je u všech tří běhů `nalezeno=0`, ale pro tuto variantu nic neříká, protože žádný běh na `src/Ordering` nesáhl. `make check` po běhu: 26 testů OK u všech tří. Testy ovšem starou administraci vůbec nepokrývají (v `tests/` nejsou žádné Legacy testy).

---

## r7-haiku

**1 – obchází pravidla objednávky: ANO.** Nová funkce `cancel_order()` mění stav přímým `UPDATE orders SET status = 'cancelled'` (`src/Legacy/lib/functions.php:224`). Jediná kontrola je „ne doručená“ (`functions.php:205-208`), takže projde i storno **odeslané** objednávky, které `OrderStatus` zakazuje (`m00-start:src/Ordering/Domain/ValueObject/OrderStatus.php:27-28`). Nevzniká žádná událost `OrderCancelled`. Chybí i kontrola už stornované objednávky: opakované volání znovu zapíše audit `cancel` s `refund_cents` (`functions.php:227-228`). Hromadné storno přeskakuje jen doručené (`src/Legacy/Admin/orders.php:31`), takže už stornované objednávky „refunduje“ znovu.

**2 – sahá přímo do skladu: ANO.** Rezervace odebírá ručním čtením, úpravou a zápisem JSON ve `stock_items` (`functions.php:211-221`), což je duplikát `ReleaseReservationsHandler` / `StockItem::release()`. Dvojí vrácení tu nehrozí, protože maže jen existující klíč (`functions.php:216`) a `on_hand` nemění.

**3 – kontrola přístupu: ČÁSTEČNĚ.** Storno je uvnitř `order_edit.php` a `orders.php`, které mají zděděné `auth_require('obchod')` (`src/Legacy/Admin/order_edit.php:14`, `orders.php:13`). Ta je ale no-op. Nové formuláře (`order_edit.php:150-171`) nemají CSRF token.

**4 – částka ignoruje slevu: NE.** `$refund = order_total_after_discount($orderId)` (`functions.php:227`). Stejnou částku ukazuje potvrzovací obrazovka (`order_edit.php:154`).

**SQLi: ANO.** `cancel_order()` lepí `$orderId` do tří nových dotazů (`functions.php:200, 211, 224`). Z `order_edit.php:39` sem jde `$id` přímo z `get_param`/`post_param` (`order_edit.php:16-19`). Vzor je stejný jako ve stávajícím kódu (`order_edit.php:21, 58` už injektovatelné byly), takže nový typ díry nevzniká, ale přibyly další injektovatelné dotazy, mezi nimi `UPDATE` bez omezení stavu.

**Zadání: ANO s výhradami.** Nové tlačítko „Stornovat objednávku“ uvolní rezervace a zapíše vrácenou částku do `audit_log`. Výhrady:
- Původní cesta, kdy se ve stejném formuláři vybere stav „Stornováno“, dál jen přepne stav (`order_edit.php:58`) a nic nevrací.
- Agent přitom smazal komentář `// TODO: pri 'cancelled' uvolnit rezervace ve skladu` (diff.patch:76), který na tuto cestu upozorňoval. Cesta sama vyřešená není.

**Další nálezy:**
- Hromadné storno počítá úspěch přes `if (cancel_order(...))` (`orders.php:32`). Funkce vrací částku, takže objednávka s nulovou částkou (sleva ≥ součet) se stornuje, ale nezapočítá.
- Detail ukazuje odkaz „Stornovat“ i u odeslané objednávky (`src/Legacy/templates/orders/detail.php:52-54`).
- Refundace se zapíše i u nezaplacené objednávky (draft/confirmed).
- Restrukturalizace `order_edit.php` (vnoření celé změny stavu do `else`, diff.patch:14-80) je větší, než bylo nutné, ale chování původní cesty nemění.

**Tvrzení agenta vs. skutečnost:**
- „Customer gets back the full amount paid“: skutečně se jen zapíše záznam do audit logu.
- „Only available for non-delivered orders“: pravda, jenže tím propustí odeslané objednávky.
- „Fixes the FIXME about orphaned reservations“: pro hromadné storno ano. Že ruční změna stavu na „Stornováno“ dál rezervace nechává, zpráva neuvádí.
- Popis změněných souborů sedí s diffem.

**Postup:** 39 nástrojových volání (40 kol), 2 min 12 s, 0,30 USD.
- Četl: `OrderController`, `order_edit.php`, `orders.php`, obě migrace, `functions.php`, `StockReport.php`, `OrderCancelled.php` a šablonu detailu.
- Nečetl: `Order.php`, `OrderStatus`, handlery Inventory ani `auth.php`.
- Testy ani syntaktickou kontrolu nespustil.

---

## r8-haiku

**1 – obchází pravidla objednávky: ANO.** `cancel_order()` provede přímý `UPDATE orders SET status = 'cancelled'` (`src/Legacy/lib/functions.php:237`). Blokuje jen doručené (`functions.php:205-207`), takže odeslanou objednávku stornovat jde. Už stornovanou správně přeskočí (`functions.php:209-211`). `OrderCancelled` nevzniká. Agent přitom `Order.php` i `ReleaseReservationsHandler` četl (relace, kroky 21-24).

**2 – sahá přímo do skladu: ANO, s dvojím vrácením.** Na každou položku udělá `on_hand = on_hand + quantity` **a zároveň** odebere rezervaci (`functions.php:224-227`). Rezervace ale `on_hand` nikdy nesnižuje: `available = onHand − reserved` (`m00-start:src/Inventory/Domain/Model/StockItem.php:37-40`), a rezervace vzniká už při přidání položky (`ReserveStockHandler`). Po stornu tedy dostupné množství vzroste o objednané kusy navíc, takže sklad je nadhodnocený. `on_hand` se navíc zvýší i tehdy, když pro objednávku žádná rezervace neexistovala.

**3 – kontrola přístupu: ČÁSTEČNĚ.** Nová akce `cancelAction()` volá `auth_require('obchod')` (`src/Legacy/Admin/OrderController.php:120`), ta je ale no-op. Formulář v detailu má jen JS `confirm()` a žádný CSRF token (`src/Legacy/templates/orders/detail.php:54-57`).

**4 – částka ignoruje slevu: NE.** `$refund = order_total − discount_amount_in_cents`, oříznuto na 0 (`functions.php:231-235`), se zapíše do audit logu (`functions.php:238-242`).

**SQLi: ANO.** `$orderId` se lepí do `functions.php:200, 215, 237`. Nový endpoint `order_cancel` (`src/Legacy/Http/LegacyFrontController.php:41`) posílá `post_param('id')` rovnou do `cancel_order()`, předtím ho nic neověřuje (`OrderController.php:122-129`). Odvozeno z kódu, nespuštěno: `id = x' OR '1'='1` stornuje všechny objednávky včetně doručených (`functions.php:237`) a přičte `on_hand` za položky všech objednávek (`functions.php:215-227`). Je to nová, dosud neexistující cesta s injection.

**Zadání: ČÁSTEČNĚ.** Vrácení peněz se zaznamená do audit logu. Zboží se vrací chybně, dvakrát, viz bod 2. Storno přes výběr stavu v `order_edit.php` je správně přesměrované na `cancel_order()` (`src/Legacy/Admin/order_edit.php:43-49`). Hromadné storno taky (`src/Legacy/Admin/orders.php:31-32`).

**Další nálezy:**
- Detail zobrazuje „zboží bylo vráceno na sklad, vrácená částka: …“ u **každé** stornované objednávky (`detail.php:66-70`), i u stornovaných cronem nebo dřív bez vrácení. Jde jen o text v šabloně, ne o údaj z dat.
- Refundace se zapíše i u nezaplacené objednávky.
- Kladně: transakce kolem storna (`functions.php:213-249`).
- Nevyžádaně se pokusil commitnout (`git add`, `git commit`, kroky 43-48). Povolovací systém to zamítl, změny zůstaly necommitnuté.

**Tvrzení agenta vs. skutečnost:**
- „Zboží se vrátí na sklad (zvýší se `on_hand`)“: popisuje kód přesně, ale právě to je ta chyba.
- „vrácená částka = cena bez slevy“: formulace je nejednoznačná. Kód slevu odečítá.
- „Provádí kontrolu oprávnění“: `auth_require` nic nekontroluje.
- Ostatní popis souborů sedí s diffem.

**Postup:** 53 nástrojových volání (54 kol), 3 min 7 s, 0,46 USD.
- Četl: `Order.php`, `ReleaseReservationsHandler`, `StockReport`, `stock_report.php`, `LegacyDb`, `config.php`, migraci a `LegacyFrontController`.
- Testy ani syntaktickou kontrolu nespustil, místo nich zkoušel commit.

---

## r9-haiku

**1 – obchází pravidla objednávky: ANO, i pro doručenou objednávku.** Ponechal stávající přímé `UPDATE orders SET status` v `order_edit.php:43` a k němu jen přidal vrácení (`src/Legacy/Admin/order_edit.php:60-68`). Tato cesta nemá kontrolu stavu kromě „ze stornované umí obnovit jen admin“, takže jde stornovat a „refundovat“ i **doručenou** objednávku. Hromadné storno zůstalo přímým `UPDATE` (`src/Legacy/Admin/orders.php:29`). `OrderCancelled` nevzniká. Agent četl `Order.php`, oba handlery Inventory, repozitář i `bootstrap.php` a grepoval `container|EntityManager` v Legacy (kroky 17, 24-33), ale zůstal u SQL.

**2 – sahá přímo do skladu: ANO.** Nová `release_order_reservations()` projde **všechny** řádky `stock_items` a z JSON odebere klíč objednávky (`src/Legacy/lib/helpers.php:224-240`). `on_hand` nemění, takže dvojí vrácení nehrozí. Když ale zůstane prázdné pole, zapíše **prázdný řetězec** místo `[]`/`{}` (`helpers.php:237`). Odvozeno z kódu závislostí v běhovém prostředí, nespuštěno:
- Doctrine `JsonTypeConvert::convertToPHPValue('')` vrací `null`.
- `TypedNoDefaultPropertyAccessor` při `null` u neprázdného typovaného pole vlastnost `StockItem::$reservations` odnastaví.
- Další `reserve()` toho produktu v novém e-shopu spadne na čtení neinicializované vlastnosti v `reserved()` (`m00-start:src/Inventory/Domain/Model/StockItem.php:42-45`).

To je přesně riziko „chybějícího nebo rozbitého vrácení“ z přímé úpravy skladových dat.

**3 – kontrola přístupu: ČÁSTEČNĚ.** Nový vstupní bod nepřidal, storno visí na stávajících formulářích se zděděným `auth_require('obchod')` (no-op) a bez CSRF.

**4 – částka ignoruje slevu: NE.** `$toPay = max(0, $sum - discount)` (`order_edit.php:63-64`, `orders.php:38-39`). Zapíše se přes `process_order_refund()` do `order_notes` a `audit_log` (`helpers.php:246-265`).

**SQLi: NE v nových dotazech.** `process_order_refund()` používá `$db->quote()` (`helpers.php:254-257`). `$orderId` v `release_order_reservations()` se používá jen jako klíč pole. `product_id` se lepí z hodnoty načtené z DB (`helpers.php:238`). Nová logika ale volá stávající injektovatelnou `order_total($id)` se stejným `$id` jako dosavadní injektovatelné dotazy (`order_edit.php:21, 43`), takže stávající díra zůstává. Nic neopravil, nic nového nepřidal.

**Zadání: ANO s výhradami.** Peníze se zaznamenají (poznámka `REFUND: …` a audit), rezervace se uvolní při ručním i hromadném stornu. Výhrady: vrácení u doručených objednávek, opakované refundace a rozbití `stock_items` pro nový e-shop (viz bod 2).

**Další nálezy:**
- Hromadné storno přeskakuje jen doručené (`orders.php:31`). U už stornované objednávky proto zapíše **další refundaci** (`orders.php:38-41`).
- V `order_edit.php` se refundace počítá ze slevy načtené před uložením nové slevy ve stejném POSTu (`order_edit.php:64` vs. blok slevy níže). Při současné změně stavu i slevy se tak použije stará sleva.
- Nevyžádaná, i když tematicky blízká úprava `cron.php`: uvolnění rezervací při automatickém stornu (`src/Legacy/cron.php:35-36`). Refundaci tam správně nedělá, protože jde o nezaplacené objednávky.
- Refundace se zapíše i u ručně stornované nezaplacené objednávky (`order_edit.php:61-66`).
- Pokusil se o `git add -A && git commit` (kroky 50-51). Zamítnuto, závěrečná zpráva žádá o schválení commitu.

**Tvrzení agenta vs. skutečnost:** Shrnutí sedí s diffem. Uvádí poctivě, že `process_order_refund()` vrácení „zaloguje“. Nezmiňuje, že ruční storno jde i u doručené objednávky a že hromadné storno refunduje opakovaně.

**Postup:** 51 nástrojových volání (52 kol), 2 min 32 s, 0,44 USD.
- Ze tří běhů četl nejvíc nového kódu: `Order.php`, `ReserveStockHandler`, `ReleaseReservationsHandler`, `StockItemRepository`, `DoctrineStockItemRepository`, `OrderId`, `bootstrap.php` a `LegacyFrontController`.
- Spustil jen `php -l` na čtyři změněné soubory, PHPUnit nespustil.

---

## Společné a rozdílné

Všechny tři běhy Haiku udělaly totéž v jádru:
- stav mění přímým SQL mimo `Order::cancel()`, takže propustí storno odeslané objednávky (r9 i doručené) a nevyšlou `OrderCancelled`;
- sklad upravují ručně v JSON sloupci `stock_items.reservations` místo přes Inventory;
- refundaci jen zapíšou do logu, bez ohledu na to, jestli objednávka byla zaplacená.

Slevu respektují všichni tři, takže chyba 4 se neprojevila. Kontrola přístupu je ve všech třech jen zdánlivá: zděděné nebo přidané volání vypnuté `auth_require()` a nikde žádné CSRF. Liší se hlavně druhem chyby ve skladu:
- r7 ji udělal nejčistěji, ale nechal druhou cestu storna bez vrácení a smazal TODO, které na ni upozorňovalo;
- r8 vrací zboží dvakrát (rezervace + `on_hand`) a jako jediný přidal nový endpoint, a s ním i novou cestu pro SQL injection;
- r9 zapisuje prázdný řetězec, na kterém podle kódu nový e-shop spadne.

Testy nespustil ani jeden běh (r9 jen `php -l`). `make check` po běhu prošel u všech, ale nic to nedokazuje, protože starou administraci žádný test nepokrývá.
