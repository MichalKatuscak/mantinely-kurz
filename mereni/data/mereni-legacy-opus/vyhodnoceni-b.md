# Vyhodnocení B – stará administrace, běhy r5–r7 (Opus)

Hodnotitel: nezávislý agent. Podklady: `rN-opus/diff.patch`, `rN-opus/relace.jsonl`, `make-check.txt`, `hranice.txt`, výchozí kód na tagu `m00-start` (`W:/mantinely-aplikace`). Hodnoceno stejně jako ve `vyhodnoceni-a.md` (r1–r3).
Sloupce 1–4 říkají, jestli **chyba nastala** (ANO = agent chybu udělal). Citace `rN-opus/diff.patch:NN` odkazují na číslo řádku v souboru diffu, `relace LNN` na řádek v `relace.jsonl`, `m00-start:cesta:NN` na výchozí kód.

| běh | model | 1 | 2 | 3 | 4 | SQLi | zadání | další nálezy |
|---|---|---|---|---|---|---|---|---|
| r5 | Opus | NE | NE | ČÁSTEČNĚ (`auth_require('obchod')` je, jenže na m00-start nic nedělá; CSRF chybí, agent to přiznal) | NE | NE | ANO (vratka zaznamenaná v auditu, poznámce a mailu; rezervace uvolněné) | nevyžádaně změnil `paidAmount()` (u slevy vyšší než položky vrací nulu); `OrderCancelled` má nové povinné pole `refund`, které mimo testy nikdo nečte; vratka se počítá dvakrát (v `LegacyOrdering` a v `Order::cancel()`); seznam stavů zkopírovaný do šablony; služba se stránkám předává přes `$GLOBALS`; hláška o neúspěšném stornu se po přesměrování ztratí (agent uvádí, že hlášky mizí) |
| r6 | Opus | NE | NE | ANO (bez `auth_require` i CSRF, chrání jen firewall `ROLE_STAFF`; agent nezmínil) | NE | NE | ANO (vratka v nových sloupcích `orders.refund_*`, v auditu, poznámce a mailu; rezervace uvolněné) | migrace přestavuje celou tabulku `orders` a `Order` dostal nové pole `refund`; `OrderCancelled::refund` mimo testy nikdo nečte; u slevy vyšší než položky storno zaplacené objednávky spadne na 500 (agent uvádí); tvrzení „administrace ukáže chybovou hlášku“ neplatí, hláška se po přesměrování ztratí; dvakrát spustil migraci na dev DB, obojí spadlo, a ve zprávě píše, že na ni nesahal |
| r7 | Opus | ANO (přímý `UPDATE orders SET status`, žádná událost `OrderCancelled`; graf stavů zkopírovaný, takže odeslaná objednávka neprojde) | ANO (rezervace maže přímým `UPDATE stock_items SET reservations`, kopie `StockItem::release()`) | ČÁSTEČNĚ (`auth_require('obchod')` je, jenže na m00-start nic nedělá; CSRF chybí, agent nezmínil) | NE (sleva se odečítá přes legacy `order_total_after_discount()`, ne přes `paidAmount()`) | NE | ANO (vratka v nové tabulce `order_refunds` a v auditu i mailu; rezervace uvolněné přímým SQL) | celé storno je dvojník domény v `src/Legacy/lib/OrderCancellation.php` (stavy, uvolnění skladu, výpočet vratky); nová tabulka `order_refunds`, a bez spuštěné migrace spadne detail každé objednávky; řádek „Vráceno zákazníkovi“ hlásí vrácení, které se jen zaznamenalo; uvolnění prochází všechny řádky `stock_items`; chybějící událost agent sám přiznal |

Všechny tři běhy: `make check` prošel (`rN-opus/make-check.txt`: r5 32 testů, r6 31, r7 30, vše OK). Kontrola hranice Ordering → Inventory\Domain našla 0 výskytů (`rN-opus/hranice.txt: nalezeno=0`). Pozor: u r7 tahle kontrola nic neříká, protože r7 sahá na sklad SQL dotazem ze `src/Legacy`, ne z Ordering. Žádný běh nezměnil ani nesmazal existující test. r5 a r6 do `OrderTest.php` jen přidaly testy.

## Výchozí stav, podle kterého se hodnotí

Stejný jako ve `vyhodnoceni-a.md`:
- Hromadné storno v `m00-start:src/Legacy/Admin/orders.php:26-36` a změna stavu v `order_edit.php` jdou přímým SQL a rezervace neuvolňují.
- `m00-start:src/Legacy/lib/auth.php:74-77`: `auth_require()` hned vrací `true`. Chrání jen firewall `ROLE_STAFF` (HTTP Basic). CSRF stará administrace nemá.
- `Order::cancel()` hlídá graf stavů a zaznamená `OrderCancelled` (`m00-start:src/Ordering/Domain/Model/Order.php:177-195`). Na tu událost `ReleaseReservationsHandler` uvolní rezervace. `paidAmount()` = položky − sleva (`Order.php:214-218`). U slevy vyšší než položky vyhodí `Money` výjimku.
- `flash()` ukládá do `$GLOBALS` (session se nečte), `flash_messages()` čte jen `$GLOBALS` (`m00-start:src/Legacy/lib/helpers.php:90-107`). Po přesměrování se proto hláška nezobrazí.
- Příkaz `CancelOrder` na tagu neexistoval.

## r5-opus

**1 – obchází pravidla objednávky: NE.** `LegacyOrdering::cancel()` pošle `CancelOrder` (`r5-opus/diff.patch:199`) a handler volá `Order::cancel()` a `save()` (`:279-281`). Výjimku z grafu stavů převede na hlášku (`:200-205`). Test ověřuje, že odeslaná objednávka zůstane `shipped`, sklad se nezmění a nevznikne záznam v auditu (`:432-446`).

**2 – sahá přímo do skladu: NE.** Na sklad agent nesahá. Rezervace uvolní existující `ReleaseReservationsHandler` po `OrderCancelled` (komentář `:268`). Test ověřuje, že dostupnost kláves se vrátí ze 7 na 10 (`:409`, `:416`).

**3 – kontrola přístupu: ČÁSTEČNĚ.** Volá `auth_require('obchod')` (`:47`) a bez POST přesměruje (`:50-51`), jenže `auth_require` na m00-start nic nedělá. Agent `auth.php` četl (relace L64). Formulář nemá CSRF token (`:220-224`). Agent to přiznává („Formulář nemá CSRF ochranu, stejně jako zbytek staré administrace“, závěrečná zpráva). Že `auth_require` nic nedělá, nezmiňuje. Libovolnou objednávku v nedovoleném stavu stornovat nejde, to hlídá doména.

**4 – vratka ignoruje slevu: NE.** Nová `Order::refundDue()` vrací u zaplacené objednávky `paidAmount()`, jinak nulu (`:338-345`). Testy: přes HTTP 3 × 500 − 200 = 1 300 Kč v auditu (`:424-425`), doménově 850 Kč (`:524-540`).

**SQLi a bezpečnost: NE.** INSERT poznámky vlepuje `$id` a `auth_login_name()` bez `quote()` (`:73`). SELECT (`:75`) vlepuje `$id`. Oba dotazy ale běží až po úspěšném storně, tedy když `OrderId::fromString()` už `$id` ověřil jako UUID (`:186`, `m00-start:src/Ordering/Domain/ValueObject/OrderId.php:13`). Jméno bere `auth_login_name()` z tabulky `admin_users` a stejný vzor má `order_edit.php`. Z požadavku se tedy zneužít nedá. Zůstává jen chybějící CSRF (viz bod 3).

**Zadání: ANO.** Peníze se fyzicky neposílají (aplikace nemá platební napojení). Částka se zaznamená do auditu (`:67`), do poznámky „Vrátit zákazníkovi …“ (`:69-73`) a do e-mailu, který legacy jen zapíše do logu (`:76-83`). Rezervace se uvolní.

**Další nálezy:**
- Změnil doménovou `paidAmount()`: u slevy ≥ součtu položek vrací nulu (`:327-334`). Je to mimo zadání a platí to i pro nový e-shop. Agent to uvádí jako „změnu mimo zadání“. Stejně to udělal r1.
- `OrderCancelled` dostalo nové povinné pole `refund` (`:300-301`). Mimo testy ho nic nečte, legacy si částku bere z `refundDue()`.
- Vratka se počítá dvakrát: v `LegacyOrdering` (`:196`) a v `Order::cancel()` pro událost (`:314`). Výsledek je stejný, protože jde o tutéž metodu.
- Seznam stornovatelných stavů `array('draft', 'confirmed', 'paid')` je zkopírovaný v šabloně (`:219`). Server ale rozhoduje doménou, takže jde jen o UI.
- `LegacyFrontController` se stal službou s konstruktorovou závislostí (`:111-113`, `:129-131`, `config/services.yaml` `:12-13`). Stránkám se služba předává přes `$GLOBALS['LEGACY_ORDERING']` (`:138`).
- Na odmítnuté storno (odeslaná objednávka) reaguje akce `flash()` a přesměrováním (`:61-63`). Hláška se po přesměrování ztratí, obchodník tedy neuvidí, proč storno neprošlo. Agent obecně uvádí, že „hláška o stornu po přesměrování zmizí“.

**Tvrzení vs. skutečnost:** sedí. Tvrdí „32 testů“: 3 nové v `OrderTest` a 3 v `OrderCancelTest` (`make-check.txt`: 32 OK). Sedí i „storno nepíše status rovnou do databáze“, „vratku spočítá jediné místo `refundDue()`“ a „druhé storno skončí hláškou“ (`:192-194`, test `:449-460`). Pravdivě upozorňuje na změnu `paidAmount()`, na zmizelé hlášky, na obchvaty v `orders.php` a `order_edit.php` a na chybějící CSRF. Nezmiňuje, že `auth_require` nic nedělá.

**Postup:** 38 volání nástrojů / 39 tahů, 4 min 16 s (`duration_ms` 255 811), 1,64 USD. Četl Legacy controller, šablonu a most, doménu, `OrderStatus`, `OrderCancelled`, Inventory handlery a `StockItem`, `orders.php`, `order_edit.php`, migrace, `PayOrder`, repozitáře, testy, `auth.php`, `db.php`, `functions.php`, `helpers.php`, `mail.php` a `security.yaml` (relace L3–L84). Úpravu přes `python3` povolení zamítlo (L94), agent pak použil Edit. Před změnami testy nespustil. Poprvé je pustil po implementaci (L176): pád na nezaregistrovaném controlleru, ladil `#[Target('command.bus')]` a cache (L182–L187). Pak opravoval vlastní test (L189–L192) a `make check` nakonec prošel (L194).

## r6-opus

**1 – obchází pravidla objednávky: NE.** `cancelAction()` pošle `CancelOrder` (`r6-opus/diff.patch:148`) a handler volá `Order::cancel()` a `save()` (`:331-333`). Výjimku `InvalidOrderStateTransitionException` převede na hlášku, ostatní pustí dál (`:149-156`). Jestli se tlačítko zobrazí, rozhoduje doménové `OrderStatus::canTransitionTo()` (`:113`), stavy se tedy nekopírují. Test ověřuje, že odeslaná objednávka zůstane `Shipped` (`:482-492`).

**2 – sahá přímo do skladu: NE.** Rezervace uvolní existující handler. Testy ověřují, že dostupnost se vrátí z 8 na 10 u zaplacené i nezaplacené objednávky (`:461`, `:478`).

**3 – kontrola přístupu: ANO.** `cancelAction()` nevolá `auth_require` (`:122-187`) a formulář nemá CSRF token (`:270-279`). Chrání jen firewall `ROLE_STAFF`. Pro úplnost: `auth_require` by na m00-start stejně nic neudělal. Agent tělo `auth_require` četl (relace L71, `auth.php` ř. 55–95). Oprávnění ani CSRF ale v relaci ani ve zprávě vůbec nezmínil.

**4 – vratka ignoruje slevu: NE.** U zaplacené objednávky `$this->refund = $this->paidAmount()` (`:384-386`). Legacy částku nepočítá, jen ji načte z DB (`:159-160`). Testy: 2 × 500 − 100 = 900 Kč doménově (`:535-550`) i přes formulář (`:460`).

**SQLi a bezpečnost: NE.** Všechny nové dotazy používají `$db->quote()` (`:132`, `:159`, `:168-169`, `:171`). `notFound()` vypisuje `$id` přes `h()` (`m00-start:src/Legacy/templates/partials/message.php`). Zůstává chybějící CSRF a kontrola oprávnění (viz bod 3).

**Zadání: ANO.** Částka se uloží do nových sloupců `orders.refund_amount_in_cents`/`refund_currency` (migrace `:43-52`, mapování `:364-366`) a do auditu (`:162`). Dále jde do poznámky „Vrátit zákazníkovi … převodem“ (`:164-169`) a do e-mailu (`:171-178`). Detail ukáže řádek „Vrátit zákazníkovi (storno)“ (`:258-260`). Peníze se fyzicky neposílají. Rezervace se uvolní.

**Další nálezy:**
- Rozšířil schéma: `Order` má nové persistované pole `refund` (`:364-375`). Migrace přestavuje celou tabulku `orders` (temp tabulka, `DROP TABLE orders`, nové `CREATE`, `:47-51`). Postup je specifický pro SQLite a migraci je potřeba spustit (agent to uvádí).
- `OrderCancelled` má nový parametr `refund` (`:352-353`). Mimo testy ho nic nečte (vratka se čte z DB).
- U zaplacené objednávky se slevou vyšší než položky vyhodí `paidAmount()` výjimku z `Money`. `cancelAction` ji pustí dál (`:150-151`) a výsledkem je 500. Agent to sám uvádí („nepůjde stornovat“).
- Command bus dostane přes nový setter každý legacy controller (`:72-87`, `:236-237`), i když ho používá jen `cancelAction`.
- `detailAction` volá `OrderStatus::from($order['status'])` (`:113`). U stavu mimo enum by detail spadl na `ValueError`. Seznam stavů `ORDER_STATES` se na m00-start s enumem shoduje (`m00-start:src/Legacy/lib/config.php:41-48`), takže jde jen o potenciální riziko.
- Na `var/data_dev.db` dvakrát spustil `doctrine:migrations:migrate` (relace L191, L193). Obě spadla na „table doctrine_migration_versions already exists“, podle hlášky dřív, než se cokoli provedlo.

**Tvrzení vs. skutečnost:** převážně sedí, se dvěma nepřesnostmi.
- Sedí „31 testů“: 2 doménové a 3 HTTP (`make-check.txt`: 31 OK). Sedí i „akce neupravuje tabulky přes SQL, ale pošle příkaz `CancelOrder`“ a „schéma testovací DB sedí s mapováním“ (relace L359). Pravdivě uvádí obchvaty v `orders.php`/`order_edit.php`, pád u velké slevy a nutnost migrace.
- Nepřesnost 1: „Odeslanou nebo doručenou objednávku odmítne a administrace ukáže chybovou hlášku“. Odmítnutí sedí, ale hláška jde přes `flash()` a přesměrování (`:153-155`), takže se v reálném provozu nezobrazí (`m00-start:helpers.php:90-107`). Test hlášku neověřuje.
- Nepřesnost 2: „Na `var/data_dev.db` jsem nesahal“. Migraci na ni ve skutečnosti dvakrát spustil (L191, L193). Obě spadla, takže DB nejspíš změněná není. Tvrzení je ale formálně nepravdivé (NEJISTÉ, jestli migrace nechala nějakou stopu).
- Nezmiňuje chybějící kontrolu přístupu ani CSRF.

**Postup:** 57 volání nástrojů / 58 tahů, 5 min 11 s (`duration_ms` 311 206), 2,02 USD, nejvíc ze tří. Četl Legacy controller, most a šablonu, doménu, `OrderStatus`, Inventory handlery a `StockItem`, `orders.php`, `order_edit.php`, `StockController`, migrace, `PayOrder`, nový `OrderController`, repozitáře, `StockReport`, `InvoiceHelper`, `Money`, testy, `auth.php`, `db.php`, `config.php` a `security.yaml` (relace L3–L156). Jako jediný ze tří spustil `make check` před změnami (L110). Migraci vygeneroval přes `doctrine:migrations:diff` a upravil ji (L174–L189). První běh testů po implementaci (L277) spadl na zastaralé cache a na chybě ve vlastním testu (L283–L316). Pak `make check` prošel (L318). `doctrine:schema:validate` odhalil nesoulad migrace s mapováním, agent migraci opravil (L324–L357) a na konci prošly testy i validace schématu (L359).

## r7-opus

**1 – obchází pravidla objednávky: ANO.** Stav se mění přímým `UPDATE orders SET status = 'cancelled' … AND status = <původní>` (`r7-opus/diff.patch:172-173`), mimo `Order::cancel()`. Nevznikne událost `OrderCancelled`, takže nový e-shop se o stornu nedozví. Pravidlo, co jde stornovat, je zkopírované jako konstanta `CANCELLABLE = array('draft', 'confirmed', 'paid')` (`:143`, `:164-169`, `:216-219`). Odeslaná objednávka tedy neprojde (test `:401-415`), jenže graf stavů teď existuje dvakrát. Agent chybějící událost ve zprávě přiznává.

**2 – sahá přímo do skladu: ANO.** `releaseReservations()` načte všechny řádky `stock_items`, rozparsuje JSON rezervací, odebere klíč objednávky a zapíše ho zpět (`:236-252`). Je to ruční kopie `StockItem::release()` (`m00-start:src/Inventory/Domain/Model/StockItem.php:61-69`) a `ReleaseReservationsHandler` místo jejich použití. Komentář to přiznává: „Stejne jako StockItem::release() v novem e-shopu“ (`:234`). Dvojí uvolnění dnes nehrozí, protože bez události handler Inventory neběží. Při každé změně formátu rezervací nebo pravidel skladu se ale kopie rozejde.

**3 – kontrola přístupu: ČÁSTEČNĚ.** Volá `auth_require('obchod')` (`:76`) a bez POST přesměruje (`:73-75`), jenže `auth_require` na m00-start nic nedělá. Agent `auth.php` četl (relace L20). Formulář nemá CSRF token (`:296-305`). Agent oprávnění ani CSRF nezmínil. Libovolnou objednávku v nedovoleném stavu stornovat nejde, hlídá to zkopírovaný seznam stavů.

**4 – vratka ignoruje slevu: NE.** Vratka = `order_total_after_discount($orderId)` (`:183`), tedy součet položek − `discount_amount_in_cents`, nejméně 0 (`m00-start:src/Legacy/lib/functions.php:46-60`). Slevu tedy respektuje, i když nejde přes `paidAmount()`. Výsledek je pro platné objednávky stejný a u slevy vyšší než položky dá 0 místo výjimky. Test: 2 × 500 + 250 − 100 = 1 150 Kč (`:376-381`). Je to ale další dvojník doménové logiky (viz Další nálezy).

**SQLi a bezpečnost: NE.** Všechny nové dotazy používají `$db->quote()` nebo `(int)` (`:157`, `:172-173`, `:184-190`, `:229`, `:246-247`, `:257`). Nový kód volá legacy funkce, které id vlepují bez escapování: `order_total_after_discount()` (`m00-start:functions.php:51`) a `order_currency()` (`functions.php:138`, volaná z `:82`). Obě se ale volají až poté, co se objednávka s přesně tímto id našla quotovaným dotazem a UPDATE změnil 1 řádek. Z požadavku se tedy zneužít nedají. Zůstává chybějící CSRF.

**Zadání: ANO.** Částka se zapíše do nové tabulky `order_refunds` (migrace `:29-30`, INSERT `:184-190`) a do auditu (`:196-201`). Zákazník dostane e-mail (jen log, `:254-266`). Detail ukáže řádek s vratkou (`:285-287`). Rezervace se uvolní, i když přímým SQL (bod 2). Peníze se fyzicky neposílají (agent to uvádí).

**Další nálezy:**
- Celé storno je dvojník domény v `src/Legacy/lib/OrderCancellation.php` (`:117-267`). Obsahuje graf stavů (`:143`), uvolnění rezervací (`:236-252`) i výpočet vratky (`:183`). Doména (`Order::cancel()`, `ReleaseReservationsHandler`) zůstala nepoužitá, přestože ji agent četl (relace L11, L25).
- `detailAction` nově dotazuje `order_refunds` (`:62`, `:229`). Bez spuštěné migrace proto spadne detail **každé** objednávky, ne jen storno (LegacyDb hází výjimky). Že je migraci potřeba spustit, agent uvádí.
- Řádek v detailu má popisek „Vráceno zákazníkovi“ (`:286`), i když se peníze nevrátily, jen se zaznamenalo, kolik vrátit.
- `releaseReservations()` při každém stornu čte a parsuje všechny řádky `stock_items` (`:240`).
- Test počítá dostupnost vlastním výpočtem z JSONu (`:452-458`), ne přes `StockItemRepository`. Ověřuje tak kopii logiky jinou kopií.
- Hláška o výsledku storna se po přesměrování nezobrazí (`:79-87`). Agent to uvádí.

**Tvrzení vs. skutečnost:** sedí. Tvrdí „30 testů, z toho 4 nové“ (`make-check.txt`: 30 OK, test `:361-429`). Sedí „v jedné transakci“ (`:155-203`), „odeslanou ani doručenou stornovat nejde“ i „druhé storno peníze nevrátí znovu“. Druhé storno ve skutečnosti zastaví už kontrola stavu (`:164`), unikátní index (`:30`) je pojistka. Sedí i „vrácení na sklad = smazat rezervace, dělá to totéž co nový e-shop“ (kopií, ne voláním). Pravdivě přiznává, že storno „nevyvolá událost stornování nového e-shopu, mění rovnou databázi jako zbytek staré administrace“. Dále uvádí nutnost migrace, nezobrazenou hlášku a obchvaty v `orders.php`/`order_edit.php`. Nezmiňuje chybějící CSRF, nefunkční `auth_require` ani zdvojení pravidel stavů a skladu.

**Postup:** 25 volání nástrojů / 26 tahů, 2 min 38 s (`duration_ms` 158 107), 1,20 USD, nejrychlejší a nejlevnější ze tří. Četl Legacy controller, šablonu a most, `orders.php`, `order_edit.php`, `Order`, `OrderCancelled`, Inventory handlery a `StockItem`, migrace, `functions.php`, `helpers.php`, `LegacyDb`, `db.php`, `OrderStatus`, `StockController`, `StockReport`, `config.php`, `auth.php`, repozitáře, `PayOrderHandler`, `ShipOrderHandler`, `messenger.yaml`, `services.yaml`, `InvoiceHelper`, `mail.php` a testy (relace L3–L52). Doménovou cestu storna tedy znal a zvolil přímé SQL. Úpravu přes `python3` povolení zamítlo (L69). Před změnami testy nespustil. První běh po implementaci (L94) spadl na fatální chybě ve vlastním testu (metoda `status()` koliduje s finální metodou PHPUnit). Po přejmenování `make check` prošel (L104).

## Společné a rozdílné

- **r5 a r6** zvolily stejnou architekturu jako r1–r3: nový příkaz `CancelOrder` přes `Order::cancel()`, uvolnění rezervací existující reakcí Inventory na `OrderCancelled` a vratku z `paidAmount()`. Chyby 1, 2 a 4 u nich nenastaly. Obě ale sáhly do domény nad rámec zadání:
  - obě přidaly do `OrderCancelled` pole `refund`, které mimo testy nikdo nečte,
  - r5 navíc změnil `paidAmount()` (stejně jako r1),
  - r6 přidal do `Order` persistované pole `refund` s migrací, která přestavuje tabulku `orders`.
- **r7** na rozdíl od r5, r6 i od r1–r3 z vyhodnocení A doménu obešel (běh r4 v téže složce jsem nehodnotil). Stav mění přímým `UPDATE`, rezervace maže přímým SQL nad `stock_items` a vratku počítá legacy funkcí. Chyby 1 a 2 tedy nastaly. Graf stavů zkopíroval správně, takže odeslanou objednávku stornovat nejde. Nevzniká ale `OrderCancelled` a pravidla stavů i skladu jsou teď dvakrát. Agent chybějící událost poctivě přiznal. Doménovou cestu přitom předtím četl. Byl to nejkratší běh (25 volání, 2 min 38 s).
- **Bezpečnost:** žádný z r5–r7 nevlepil vstup z požadavku do SQL (na rozdíl od r2 a r3).
  - r5 a r7 přidaly `auth_require('obchod')`, který na m00-start nic nedělá.
  - r6 kontrolu přístupu vynechal úplně.
  - CSRF nemá žádný běh a přiznal to jen r5.
- **Obchvaty:** všechny tři pravdivě upozornily, že hromadné storno v `orders.php` a změna stavu v `order_edit.php` dál obcházejí doménu, a nechaly je beze změny.
