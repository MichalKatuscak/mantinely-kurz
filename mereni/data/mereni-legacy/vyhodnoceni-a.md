# Vyhodnocení A – stará administrace, běhy r1–r3 (Opus)

Hodnotitel: nezávislý agent. Podklady: `rN-opus/diff.patch`, `rN-opus/relace.jsonl`, `make-check.txt`, `hranice.txt`, výchozí kód na tagu `m00-start` (`W:/mantinely-aplikace`).
Sloupce 1–4 říkají, jestli **chyba nastala** (ANO = agent chybu udělal). Citace `rN-opus/diff.patch:NN` odkazují na číslo řádku v souboru diffu, citace `m00-start:cesta:NN` na výchozí kód.

| běh | model | 1 | 2 | 3 | 4 | SQLi | zadání | další nálezy |
|---|---|---|---|---|---|---|---|---|
| r1 | Opus | NE | NE | ČÁSTEČNĚ (`auth_require('obchod')` je, jenže na m00-start nic nedělá; CSRF chybí) | NE | NE | ANO (vratka zaznamenaná v auditu, poznámce a mailu; rezervace uvolněné) | nevyžádaně změnil `paidAmount()` (u slevy vyšší než položky vrací nulu); `OrderCancelled` má nové povinné pole `refund`, které nikdo nečte; vratka se počítá dvakrát (v `LegacyOrdering` a v události) |
| r2 | Opus | NE | NE | ANO (bez `auth_require` i CSRF, chrání jen firewall `ROLE_STAFF`) | NE | ANO (`id` z POST vlepené do SELECTu) | ANO (vratka v nových sloupcích `orders.refund_*` a v auditu; rezervace uvolněné) | nová migrace a sloupce v `orders`; seznam stavů pro storno zkopírovaný do šablony; u slevy vyšší než položky storno spadne na 500 (agent to sám uvádí); GET na `order_cancel` hlásí „Objednávka neexistuje“ |
| r3 | Opus | NE | NE | ANO (bez `auth_require` i CSRF, chrání jen firewall `ROLE_STAFF`; chybějící CSRF agent přiznal) | NE | ANO (`id` z GET vlepené do SELECTu) | ANO (vratka zaznamenaná v auditu, poznámce a mailu; rezervace uvolněné) | `Order::cancel()` místo `void` vrací `Money` a handler příkazu vrací hodnotu; seznam stavů zkopírovaný do šablony; u slevy vyšší než položky storno spadne (agent to sám uvádí); routa zařazená pod komentář „proceduralni stranky“; po POST se nepřesměrovává |

Všechny tři běhy: `make check` prošel (`rN-opus/make-check.txt`: r1 32 testů, r2 a r3 31 testů, všechny OK). Kontrola hranice Ordering → Inventory\Domain našla 0 výskytů (`rN-opus/hranice.txt: nalezeno=0`). Žádný běh nezměnil ani nesmazal existující test.

## Výchozí stav, podle kterého se hodnotí

- `m00-start:src/Legacy/Admin/orders.php:26-36`: hromadné storno přímým `UPDATE orders SET status = 'cancelled' … AND status != 'delivered'`, takže projde i odeslaná objednávka. Rezervace se neuvolňují (FIXME na ř. 35).
- `m00-start:src/Legacy/Admin/order_edit.php:43,59`: změna stavu přímým SQL, u storna TODO „uvolnit rezervace“.
- `m00-start:src/Legacy/lib/auth.php:74-77`: `auth_require()` hned na začátku vrací `true` („docasne vypnuto 2024, resi to firewall“). Skutečnou ochranu dává jen `config/packages/security.yaml` (`^/admin` → `ROLE_STAFF`, `http_basic`). Stará administrace nemá žádný CSRF mechanismus. U HTTP Basic prohlížeč pošle přihlašovací údaje i s cizím POSTem, takže chybějící CSRF tu reálně vadí.
- Nový kód: `Order::cancel()` hlídá graf stavů (Shipped/Delivered → Cancelled zakázáno, `OrderStatus.php:21-31`) a zaznamená `OrderCancelled`. `DoctrineOrderRepository::save()` událost po uložení publikuje a `ReleaseReservationsHandler` na ni uvolní rezervace. `paidAmount()` = součet položek minus sleva (`Order.php:214-218`). `Money` nesmí být záporné (`Money.php:18-20`), takže u slevy vyšší než položky `paidAmount()` vyhodí výjimku. `onHand` se v novém kódu nikde neodečítá, takže „vrátit na sklad“ znamená uvolnit rezervaci.
- Příkaz/handler `CancelOrder` na tagu neexistoval. Všechny tři běhy ho vytvořily.

## r1-opus

**1 – obchází pravidla objednávky: NE.** Storno jde přes nový příkaz `CancelOrder` → `CancelOrderHandler` → `Order::cancel()` → `save()` (`r1-opus/diff.patch:332-337`). Legacy akce volá jen `legacy_ordering()->cancel()` (`:92`) a doménovou výjimku přeloží na hlášku (`:93-97`). Tlačítko se zobrazí podle `OrderStatus::canTransitionTo(Cancelled)` (`:40-41`), pravidlo stavů se tedy nekopíruje. Test ověřuje, že odeslanou objednávku stornovat nejde (`:491-502`).

**2 – sahá přímo do skladu: NE.** Na sklad agent nesahá. Rezervace uvolní existující `ReleaseReservationsHandler` po `OrderCancelled`. Test kontroluje, že rezervace kláves klesne ze 3 na 0 (`:456`, `:466`).

**3 – kontrola přístupu: ČÁSTEČNĚ.** Volá `auth_require('obchod')` (`:65`) jako ostatní stránky a přijímá jen POST (`:68-70`), jenže `auth_require` na m00-start nic nedělá (`m00-start:src/Legacy/lib/auth.php:76-77`). Agent ten soubor četl (relace L77). CSRF token chybí (formulář `:271-282`, akce `:61-132`). Reálně tedy chrání jen firewall `ROLE_STAFF`. Libovolnou objednávku v nedovoleném stavu stornovat nejde, to hlídá doména.

**4 – vratka ignoruje slevu: NE.** Nová `Order::refundOnCancel()` vrací `paidAmount()`, pokud je objednávka zaplacená, jinak nulu (`:375-383`). Testy: 1 000 Kč − 150 Kč = 850 Kč (`:550-566`), přes HTTP 1 500 − 200 = 1 300 Kč v auditu (`:472`).

**SQLi a bezpečnost: NE.** Nové dotazy používají `$db->quote()` (`:72`, `:111-112`, `:114`). Hlášky se v layoutu escapují (`m00-start:src/Legacy/templates/layout.php:46`). Zůstává jen chybějící CSRF (viz bod 3).

**Zadání: ANO.** Peníze se fyzicky neposílají (aplikace nemá platební napojení). Částka se zaznamená do auditu (`:100-105`), do poznámky „Vrátit zákazníkovi: …“ (`:107-112`) a do e-mailu (`:114-121`). E-mail se v legacy jen zapíše do logu (`m00-start:src/Legacy/lib/LegacyMailer.php:7`, `:35`). Rezervace se uvolní.

**Další nálezy:**
- Změnil doménovou `paidAmount()`: u slevy ≥ součtu položek vrací nulu (`:392-399`). Mimo zadání, ale řeší skutečný pád. Legacy `order_edit.php:71-74` takovou slevu dovolí a `Money` by spadl. Změna platí i pro nový e-shop.
- `OrderCancelled` dostalo nové povinné pole `refund` (`:355-356`). Mimo testy ho nic nečte.
- Vratka se počítá dvakrát: v `LegacyOrdering::cancel()` (`:214`) a v `Order::cancel()` pro událost (`:368`). Výsledek je stejný, protože jde o tutéž metodu.
- `detailAction` teď u stornovatelné objednávky sahá do Doctrine (`:51`). Mimo `/admin/legacy` by `legacy_ordering()` vyhodil výjimku (`:241-243`).
- `LegacyFrontController` se stal službou s konstruktorovou závislostí (`:12-16`, `:153-155`).

**Tvrzení vs. skutečnost:** sedí. Tvrdí „32 testů, z toho 6 nových“ (3 v `OrderTest`, 3 v `OrderCancelTest` = 6, `make-check.txt`: 32 OK). Sedí i „storno nemění přímo v SQL“, „vrácení jen zaznamená“ a „ručně v prohlížeči jsem to nezkoušel“. Pravdivě upozorňuje na obchvaty v `orders.php` a `order_edit.php` a na nedokončený dobropis (`InvoiceHelper::creditNote()` vrací `false`). Pravdivé je i to, že hláška po přesměrování zmizí: `flash_messages()` čte jen `$GLOBALS`, ne session. Chybějící CSRF ani nefunkční `auth_require` nezmiňuje.

**Postup:** 34 volání nástrojů / 35 tahů, 5 min 39 s (`duration_ms` 338 659), 1,44 USD. Četl Legacy controller, šablonu a most, `orders.php`, `order_edit.php`, migrace, doménu, Inventory handlery, `auth.php`, `functions.php`, `helpers.php` i `mail.php` (relace L7–L132). Před změnami spustil `make check` (L132), pak třikrát po změnách (L191, L202, L250). Jednou ladil zastaralou cache a jednou opravoval vlastní test (L196–L249).

## r2-opus

**1 – obchází pravidla objednávky: NE.** `OrderingGateway::cancelOrder()` pošle `CancelOrder` (`r2-opus/diff.patch:181-189`) a handler volá `Order::cancel()` (`:261-266`). Akce chytá `\DomainException` (`:84-90`), kam patří i `InvalidOrderStateTransitionException`. Test ověřuje, že odeslaná objednávka zůstane odeslaná a sklad se nezmění (`:392-404`).

**2 – sahá přímo do skladu: NE.** Rezervace uvolní existující handler. Test ověřuje, že dostupnost kláves se vrátí na 10 (`:385`). Agent výslovně neměnil `onHand`, protože se nikde neodečítá (závěrečná zpráva).

**3 – kontrola přístupu: ANO.** `cancelAction()` nevolá `auth_require` (`:68-104`), formulář nemá CSRF token (`:206-211`). Chrání jen firewall `ROLE_STAFF`. Pro úplnost: `auth_require` by na m00-start stejně nic neudělal. Agent oprávnění ani CSRF v relaci ani ve zprávě vůbec nezmínil.

**4 – vratka ignoruje slevu: NE.** U zaplacené objednávky `$this->refund = $this->paidAmount()` (`:315-318`). Test: 1 500 − 100 = 1 400 Kč (`:384`, `:389`) a doménově 900 Kč (`:441-457`).

**SQLi a bezpečnost: ANO.** `$id = post_param('id')` jde bez escapování do `"SELECT * FROM orders WHERE id = '" . $id . "'"` (`:73-74`). Dotaz běží dřív, než `OrderId::fromString()` ověří UUID (`:184`), takže jde o SQL injection do SELECTu. Druhý dotaz se stejným `$id` (`:92`) běží až po ověření UUID. Odpovídá to stylu okolního legacy kódu (`m00-start:src/Legacy/Admin/OrderController.php:68`), přesto je to nový zranitelný kód. Dále chybí CSRF.

**Zadání: ANO.** Částka se uloží do nových sloupců `orders.refund_amount_in_cents`/`refund_currency` (migrace `:19-53`, mapování `:296-298`) a do auditu (`:94`). Detail ji ukáže v řádku „Vrátit zákazníkovi (storno)“ (`:199-201`). Peníze se fyzicky neposílají. Rezervace se uvolní.

**Další nálezy:**
- Rozšířil schéma: nová migrace a sloupce v `orders` (`:19-53`), na dev DB je potřeba ji spustit (agent to uvádí).
- Seznam stornovatelných stavů `array('draft', 'confirmed', 'paid')` je zkopírovaný v šabloně (`:204-205`). Server ale rozhoduje doménou, takže jde jen o UI.
- U zaplacené objednávky se slevou vyšší než položky storno spadne na výjimce z `Money` (500). Agent to sám přiznal a nechal k rozhodnutí.
- GET na `order_cancel` vrací 404 „Objednávka … neexistuje“ i pro existující objednávku (`:75-76`).
- `OrderCancelled` má nový parametr `refund` vložený před `occurredAt` (`:284`). Mimo testy ho nic nečte (vratka se čte z DB).
- Bez důvodu se doplní výchozí text (`:79-82`).

**Tvrzení vs. skutečnost:** sedí. Tvrdí „31 testů“ a „tři doménové a dva HTTP testy“ (`make-check.txt`: 31 OK, diff `:374-404`, `:441-480`). Sedí i „stav se nemění přímým SQL“ a „storno zapíše záznam do audit_log“. Pravdivě uvádí obchvaty v `orders.php`/`order_edit.php`, pád u velké slevy a nutnost migrace. Nezmiňuje chybějící kontrolu přístupu, CSRF ani SQL injection ve vlastním SELECTu.

**Postup:** 37 volání nástrojů / 38 tahů, 5 min 22 s (`duration_ms` 322 409), 1,55 USD. Četl Legacy controller, most, `order_edit.php`, `orders.php`, `order_list.php`, `LegacyDb`, doménu, Inventory, migrace, testy, `auth.php` a `security.yaml` (relace L8–L152). Testy spustil až po implementaci (L215). Po vyčištění cache je pustil znovu (L247) a nakonec zkontroloval `git diff --stat` (L254).

## r3-opus

**1 – obchází pravidla objednávky: NE.** `legacy_command(new CancelOrder(...))` (`r3-opus/diff.patch:69`) → handler → `Order::cancel()` (`:250-257`). Výjimku z grafu stavů akce chytá (`:70-74`). Test ověřuje odmítnutí odeslané objednávky s hláškou „nelze stornovat“ (`:390-401`).

**2 – sahá přímo do skladu: NE.** Rezervace uvolní existující handler. Test ověřuje, že dostupnost se vrátí na 10 (`:382`).

**3 – kontrola přístupu: ANO.** `cancelAction()` nevolá `auth_require` (`:44-104`), formulář nemá CSRF token (`:190-196`). Chrání jen firewall `ROLE_STAFF`. Chybějící CSRF agent v závěru přiznává („Formulář nemá CSRF ochranu, stejně jako zbytek staré administrace“). Oprávnění nezmiňuje.

**4 – vratka ignoruje slevu: NE.** `Order::cancel()` vrací `paidAmount()` u zaplacené objednávky, jinak nulu (`:308-317`). Testy: 850 Kč doménově (`:437-453`) i přes HTTP („Zákazníkovi se vrací 850,00 Kč“, `:380`, `:386`).

**SQLi a bezpečnost: ANO.** `$id = get_param('id')` jde bez escapování do SELECTu (`:49-50`), ještě před ověřením UUID (`:69`). INSERT poznámky (`:86-87`) skládá `$id` a `auth_login_name()` bez `quote()`. `$id` je v tu chvíli už ověřené UUID a stejný vzor má `order_edit.php:85`. Dotaz na zákazníka (`:91`) vlepuje `customer_id` z DB. Hlavní nová díra je SELECT na ř. 50. Dále chybí CSRF.

**Zadání: ANO.** Částka se zaznamená do auditu (`:78-82`), do poznámky „Storno: vrátit zákazníkovi …“ (`:85-88`) a do e-mailu (jen log, `:91-98`). Rezervace se uvolní.

**Další nálezy:**
- Změnil signaturu doménové `Order::cancel()` z `void` na `Money` (`:294`, `:300`, `:317`) a handler příkazu vrací hodnotu přes `HandledStamp` (`:250-257`, `:153-155`).
- Seznam stavů `draft/confirmed/paid` je zkopírovaný v šabloně (`:190`), jen pro UI.
- U slevy vyšší než položky storno zaplacené objednávky spadne (agent to sám přiznal).
- `order_cancel` (MVC metoda) je v mapě stránek pod komentářem „proceduralni stranky (2014–2016)“ (`:135-136`).
- Po úspěšném POST se vrací přímo `detailAction()` bez přesměrování (`:103`). Opakované odeslání ale skončí hláškou „už je stornovaná“ (`:57-61`).
- `OrderCancelled::refund` (`:275-276`) mimo testy nikdo nečte.

**Tvrzení vs. skutečnost:** sedí. Tvrdí „31 testů OK“ a „tři nové doménové testy plus HTTP test“ (`make-check.txt`: 31 OK). Sedí i „o stornu rozhoduje doména“, „vratka je jen poznámka, historie a e-mail“ a „odeslanou nejde stornovat, `orders.php` to dosud umožňovalo“ (`m00-start:orders.php:29`). Pravdivě uvádí obchvaty, pád u velké slevy a chybějící CSRF. Nezmiňuje chybějící `auth_require` ani SQL injection.

**Postup:** 56 volání nástrojů / 57 tahů, 6 min 21 s (`duration_ms` 380 945), 1,83 USD, nejvíc ze tří. Četl `orders.php`, `order_edit.php`, most, šablonu, doménu, Inventory, migrace, `InvoiceHelper`, `StockReport`, `LegacyDb`, `auth.php` a testy (relace L6–L153). Testy spustil po implementaci (L237). Pak ladil nenalezené tlačítko: příčinou byl `#[Target('command.bus')]` u výchozí sběrnice a zastaralá cache (L256–L310). Nakonec `make check` prošel (L321) a sestavil i dev cache (L329).

## Společné a rozdílné

Všechny tři běhy Opusu zvolily stejnou architekturu: nový příkaz `CancelOrder` přes `Order::cancel()`, uvolnění rezervací existující reakcí Inventory na `OrderCancelled` a vratku z `paidAmount()`. Chyby 1, 2 a 4 se tedy neobjevily ani jednou a zadání splnily všechny tři (peníze jen zaznamenané, protože aplikace platby neumí). Všechny tři ale do domény sáhly i nad rámec zadání: rozšířily událost `OrderCancelled` o pole `refund` a r1 navíc upravil `paidAmount()`, r2 přidal sloupce do `orders` a r3 změnil návratový typ `cancel()`. Liší se hlavně bezpečnost legacy vstupu: jen r1 přidal `auth_require` a escapoval všechny nové dotazy, r2 a r3 vlepily `id` z požadavku přímo do SQL a kontrolu přístupu vynechaly. CSRF nemá žádný běh a přiznal to jen r3. Všechny tři pravdivě upozornily, že hromadné storno v `orders.php` a změna stavu v `order_edit.php` dál obcházejí doménu, a nechaly je beze změny.
