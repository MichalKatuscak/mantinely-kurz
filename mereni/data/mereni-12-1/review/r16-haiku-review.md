Tohle je review změny `git diff m12-start HEAD`. Hlavní problém: polovinu zadání (zaznamenat vrácení peněz) změna nedělá, i když to zpráva autora tvrdí.

## Nálezy

**1. Vrácení zaplacené částky se nikde nezaznamená** – `src/Ordering/Application/Handler/CancelOrderHandler.php:21`, `src/Legacy/Admin/order_edit.php:47-50`
- Zadání chce u objednávky zaznamenat, že se zákazníkovi vrací zaplacená částka.
- Handler jen zavolá `Order::cancel($reason, …)`. Neuloží se žádná částka ani příznak „k vrácení“ a nevznikne k tomu žádná událost.
- V projektu nic takového není (`refund` ani podobný výraz se v `src` nenajde).
- Částka k vrácení by šla z existující `Order::paidAmount()`, jen podle toho, jestli byla objednávka zaplacená (`paid`). Draft ani `confirmed` nic nevracejí.
- Obchod tak po stornu nemá z čeho poznat, komu a kolik má peněz vrátit.

**2. Zpráva autora nesedí s diffem**
- Autor píše, že zaplacené peníze se „zaznamenají jako cancellation_reason“. Jenže `cancellation_reason` je volitelný volný text z formuláře (`order_edit.php:135`, výchozí hodnota `''`). Při hromadném stornu (`orders.php:36`) se posílá vždy `''`, takže `Order::cancel` uloží `cancellationNote = null`. O vrácení peněz se nezaznamená nic.
- Autor píše „✅ Nové testy … ověřují cancel a release reservací“. Žádný test ale nepokrývá zaplacenou objednávku, tedy ten jediný případ, kdy se peníze vracejí. Vrácení peněz netestuje nic.
- Autor píše „Všechno je hotovo“. Hotové to podle bodu 1 není.

**3. Hromadné storno už nejde u odeslaných objednávek a zpráva to neuvádí** – `src/Legacy/Admin/orders.php:28-45`
- Dřív šlo hromadně stornovat i `shipped`. Komentář na řádku 28 to výslovně připouští: „2017: i odeslané, když se balík vrátí“.
- Teď doména (`OrderStatus::Shipped => [Delivered]`) vyhodí výjimku a kód ji potichu spolkne (řádky 40-42).
- Je to změna chování, kterou zadání nežádalo. Autor ji neuvádí a obsluha nedostane žádnou chybu, jen nižší číslo v hlášce.
- Vrácený balík tak nejde stornovat vůbec a zboží se nevrátí na sklad. To je přitom přesně ten případ „zboží zpět na sklad“ ze zadání. Je potřeba se rozhodnout a říct to, ne to schovat v `catch`.
- Komentář na řádku 28 teď navíc tvrdí něco, co kód nedělá.

**4. Hromadné storno se může přerušit uprostřed** – `src/Legacy/Admin/orders.php:33-42`
- Zachytává se jen `InvalidOrderStateTransitionException`.
- Neplatné ID (`OrderId::fromString` → `\InvalidArgumentException`; `ids[]` přichází přímo z POSTu) nebo `OrderNotFoundException` z `DoctrineOrderRepository::get` shodí celou akci.
- Objednávky zpracované do té chvíle zůstanou stornované, další ne a hláška `flash` se nezobrazí.
- Dřív to byl jeden atomický `UPDATE`.

**5. V administraci dál existuje druhá cesta ke stornu mimo doménu** – `src/Legacy/cron.php:33`
- Cron pořád ruší objednávky přímo SQL: `UPDATE orders SET status = 'cancelled'`. Nevzniká `OrderCancelled` a rezervace zůstanou viset (to uvádí i `docs/legacy-mapa.md`).
- Diff převedl jen dvě ze tří cest ke stornu.
- Nemusí to být součást zadání, ale protože úloha zavádí storno s vrácením zboží, je to ta „druhá cesta ke stejnému stavu“, na kterou se má review ptát. Autor ji nezmiňuje.

**6. Události se rozesílají až po uložení, mimo transakci** – `CancelOrderHandler.php:22` spolu s `DoctrineOrderRepository::save`
- Pokud `ReleaseReservationsHandler` selže, objednávka už je uložená jako `cancelled`, rezervace zůstanou a v `order_edit.php` výjimka propadne (chytá se jen `InvalidOrderStateTransitionException`).
- Opakované storno pak nic neudělá: `cancel()` u `Cancelled` jen skončí a událost nevznikne. Rezervace se tak už nikdy neuvolní.
- Tenhle vzor v repozitáři existoval už dřív. Nový případ užití se na něm ale přímo podepisuje a test ho nepokrývá.

## Ověřeno bez nálezu
- CSRF a oprávnění: `order_edit.php` volá `auth_require('obchod')` a v POST větvi `csrf_check()` dřív, než cokoli zapíše. `orders.php` volá `auth_require` i `csrf_check` hned na začátku. `csrf_check()` (`src/Legacy/lib/csrf.php:42`) token opravdu kontroluje přes `hash_equals`.
- SQL: diff nepřidává žádné nové SQL s proměnnou. Stávající nebezpečné `UPDATE` v `order_edit.php` jen posunul o úroveň odsazení, to diff nezpůsobil.
- Doména: storno jde přes `Order::cancel()` → `OrderCancelled` → `ReleaseReservationsHandler`. Legacy používá jen příkaz, ID a výjimky, jak `src/Ordering/CLAUDE.md` dovoluje. Doménové metody, konfigurace ani existující testy se nemění.

**Neověřeno:** testy ani `make check` jsem nespustil, spuštění phpunit vyžadovalo schválení. Tvrzení autora „117 testů OK“ tedy nemám potvrzené.