# Review: storno ve staré administraci (`git diff m12-start HEAD`)

Nejzávažnější je nález 1: u zaplacené objednávky se slevou vyšší než hodnota položek storno spadne na chybu 500. Takovou slevu stará administrace dovoluje uložit. Zbytek jsou hlavně slabší místa v požadavcích a nepřesnosti ve zprávě autora.

## Nálezy

### 1. Storno zaplacené objednávky se slevou vyšší než položky skončí chybou 500
**`src/Ordering/Domain/Model/Order.php:271`**
- `cancelWithRefund()` volá `paidAmount()`, tedy `totalAmount()->subtract($discount)`. Konstruktor `Money` na záporné částce vyhodí `InvalidArgumentException` (`src/SharedKernel/Domain/Money.php:18`).
- Takovou slevu stará administrace výslovně povoluje a jen upozorní („sleva je vyšší než hodnota objednávky“, `src/Legacy/Admin/order_edit.php:73-77`).
- `cancelAction()` chytá jen `InvalidOrderStateTransitionException` (`src/Legacy/Admin/OrderController.php:192`), takže uživatel dostane 500 a objednávku stornovat nejde.
- Formulář přitom slibuje „Zákazník dostane zpět 0,00 Kč“, protože počítá `max(0, …)`.

### 2. Částka k vrácení se počítá dvakrát a každý výpočet může dát jiný výsledek
**`src/Legacy/templates/orders/detail.php:67`**
- Nápověda „Zákazník dostane zpět …“ bere `$toPay` ze staré administrace (`OrderController.php:92`: `max(0, $sum - discount)`, `discount_currency` ignoruje).
- Uložená vratka bere doménové `Order::paidAmount()`.
- Když se výpočty rozejdou (sleva vyšší než položky, sleva v jiné měně), uživatel před stornem vidí jinou částku, než jaká se zaznamená. Nápověda má brát stejný údaj jako doména.

### 3. Vrací se aktuální částka po slevě, ne to, co zákazník opravdu zaplatil
**`src/Ordering/Domain/Model/Order.php:271`**
- Slevu jde změnit přímým SQL i u zaplacené objednávky (`order_edit.php:77`).
- Když obchod po zaplacení slevu zvýší, vrátí se zákazníkovi méně, než zaplatil. Když ji sníží, vrátí se víc.
- Zadání chce vrátit „zaplacenou částku“. Částka zaplacená v okamžiku platby se nikde neukládá.

### 4. Ke stornu vedou další cesty, které nevrátí peníze ani zboží
**`src/Legacy/Admin/order_edit.php:44`, `src/Legacy/Admin/orders.php:30`, `src/Ordering/Domain/Model/Order.php:240`**
- Zadání zní „u objednávky storno: zákazník dostane zpět…“. Přímo vedle nového formuláře ale dál zůstává odkaz „Změnit stav / slevu“ (`detail.php:74`) a hromadné storno.
- Obě cesty stornují i zaplacenou objednávku přímým SQL, bez vratky a bez uvolnění rezervací. Výsledkem je stejný stav `cancelled` s různými důsledky.
- V doméně je to podobné: veřejná `cancel()` dál stornuje zaplacenou objednávku bez vratky. Ochranu, že zaplacená objednávka se stornuje jen s vratkou, nemá doména, jen nová metoda vedle ní.
- Autor to přiznává v části „mimo rozsah“. Podle zadání je to ale mezera v požadavku, ne jen technický dluh.

### 5. Po obnovení stornované objednávky jde vratku zaznamenat znovu
**`src/Ordering/Domain/Model/Order.php:268-272`, `src/Legacy/templates/orders/detail.php:57`**
- Admin smí stornovanou objednávku vrátit do stavu `paid` přes `order_edit.php:41-44`. `refund_amount_in_cents` přitom zůstane nastavený.
- Detail pak ukazuje „Vrátit zákazníkovi“ u zaplacené, nestornované objednávky.
- Další storno přes nový formulář částku přepíše a zaznamená druhé `RefundRequested`. Hrozí, že obchod peníze vrátí dvakrát.
- Tvrzení „ani opakované storno nevrátí peníze podruhé“ tedy platí jen pro storno, které jde celé přes doménu.

### 6. Zpět vrácenou zásilku už stornovat nejde
**`src/Legacy/Admin/orders.php:28`**
- Stará administrace záměrně dovolovala stornovat i odeslanou objednávku („2017: i odeslané, když se balík vrátí“).
- Nové storno odeslanou objednávku odmítne, takže pro vrácený balík neexistuje cesta k vrácení peněz.
- Je to rozhodnutí o chování. Ve zprávě autora chybí a mělo by ho schválit zadání.

### 7. Nová akce nezapisuje do historie a nemaže cache dashboardu
**`src/Legacy/Admin/OrderController.php:186-199`**
- Všechny ostatní cesty ke stornu volají `audit_log('order', …)` a `cache_delete('dashboard_stats')` (`order_edit.php:51,89`, `orders.php:33,54`, `cron.php:35`).
- Storno s vratkou se proto neobjeví v sekci „Historie“ v detailu objednávky (`OrderController.php:77`).
- Dashboard (`AdminController.php:20`) může až 120 s ukazovat tržby, ve kterých stornovaná objednávka ještě je.

### 8. Zpráva autora nesedí se stavem repozitáře
**`docs/pr.md`**
- Autor píše „Nic jsem necommitnul… na větvi `beh`“. Změna je ale commitnutá jako `b030b2e` na větvi `review`, a to jedním commitem místo slíbených tří. `CLAUDE.md` přitom chce commit po každém kroku.
- Tvrzení „`make check`: 125 testů OK“ jsem nedokázal ověřit, protože spuštění `make check` vyžadovalo schválení, které jsem nedostal.

## Prošlo kontrolou (ověřeno čtením kódu)
- **CSRF a role:** `cancelAction()` volá `auth_require('obchod')` a `csrf_check()` dřív, než cokoli zapíše. Obě funkce opravdu něco kontrolují: uživatel bez session dostane výjimku a token se porovnává přes `hash_equals`. GET jen přesměruje.
- **SQL:** jediný nový dotaz používá `$db->quote($id)`.
- **Stav objednávky a skladu:** mění se jen přes příkaz `CancelOrder`, `Order` a událost `OrderCancelled`, kterou zpracuje `ReleaseReservationsHandler`. Na `orders` ani `stock_items` není žádné SQL.
- **Existující kód:** signatura `cancel()` ani existující testy se nezměnily. Nový sloupec `refund_amount_in_cents` přidaný migrací má výchozí hodnotu 0, takže starý kód, který do `orders` vkládá řádky přes SQL, dál funguje.