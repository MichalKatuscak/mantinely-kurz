Review mám hotové. Výsledek je **neschvalovat ve stávající podobě**: dva nálezy jsou závažné, jeden porušuje pravidla projektu a zbytek jsou menší věci.

## Nálezy

### 1. Storno zaplacené objednávky spadne, když je sleva vyšší než hodnota položek (závažné)
- `src/Ordering/Domain/Model/Order.php:255` – `cancel()` teď u zaplacené objednávky volá `paidAmount()`, tedy `totalAmount()->subtract($discount)`. Když vyjde záporné číslo, `new Money(...)` vyhodí `InvalidArgumentException` (`src/SharedKernel/Domain/Money.php:18`).
- Stará administrace takovou slevu výslovně dovoluje: `src/Legacy/Admin/order_edit.php:73-77` („povolime, ale upozornime“).
- `src/Legacy/Admin/OrderController.php:191` chytá jen `InvalidOrderStateTransitionException`, takže obsluha dostane chybu 500 a objednávku stornovat nejde. Před touto změnou `cancel()` částky vůbec nepočítal, jde tedy o novou chybu.
- Šablona přitom slibuje vrácení `max(0, …)`, tedy 0 Kč (`detail.php:68`, výpočet v `OrderController.php:92`).

### 2. Druhá cesta ke stornu na stejné stránce vrácení nezaznamená (požadavek)
- `src/Legacy/templates/orders/detail.php:76` – hned pod novým formulářem dál vede odkaz „Změnit stav / slevu“ na `order_edit.php:44`. Ten nastaví `status = 'cancelled'` přímo přes SQL: nevznikne `OrderCancelled`, rezervace se neuvolní a `refund_due_in_cents` zůstane 0.
- Zadání („zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad“) tak platí jen pro jednu ze dvou cest na stejné stránce.
- Autor ve zprávě zmiňuje jen hromadné storno a cron, `order_edit` vynechal.

### 3. Změna chování doménové metody bez zastavení (pravidla projektu)
- `src/Ordering/Domain/Model/Order.php:253-256` mění chování `Order::cancel()`. CLAUDE.md říká: „Bez výslovného zadání neměň chování … `Order` … Když bez toho úkol nejde, zastav se a řekni to.“
- Autor změnu ve zprávě přiznal, ale nezastavil se. Tvrzení „You asked for this“ zadání nekryje, protože to o doméně neříká nic.

### 4. Částka k vrácení se počítá dvakrát a každý výpočet může dát jiný výsledek
- `src/Legacy/templates/orders/detail.php:68` ukazuje před stornem „zákazníkovi se vrátí“ podle `$toPay` ze staré administrace (`OrderController.php:92`, `max(0, sum − discount)`). Skutečně se ale uloží `Order::paidAmount()`.
- Výsledky se rozejdou u slevy vyšší než součet (viz nález 1). Stejný údaj se tak počítá dvěma cestami.

### 5. Řádek „Vrátit zákazníkovi“ se zobrazí i u objednávky, která už stornovaná není
- `src/Legacy/templates/orders/detail.php:57` podmiňuje řádek jen tím, že `refund_due_in_cents > 0`, na stav se nedívá.
- Admin může stornovanou objednávku vrátit zpět (`order_edit.php:41`). Hodnota sloupce přitom zůstane, takže detail dál hlásí částku k vrácení u objednávky, která znovu platí.

### 6. Zpráva autora nesedí s tím, co se stalo
- Píše „Nothing is committed“, změna je ale commit `19efec2`. Z diffu nejde poznat, kdo commit udělal; zpráva je přinejmenším zastaralá.
- Tvrzení o výsledku `make check` (123 testů, Infection 100 %) jsem neověřil, protože spuštění `make check` vyžadovalo schválení, které jsem nedostal.

### 7. Testy nepokrývají ochranu akce
- `tests/Acceptance/Legacy/CancelOrderTest.php` nemá test na odmítnutí bez platného CSRF tokenu ani pro uživatele bez role `obchod` (například `sklad`).
- Ze čtení kódu jsou kontroly v pořádku: `auth_require('obchod')` na `OrderController.php:172` opravdu kontroluje roli a `csrf_check()` na řádku 174 porovnává token přes `hash_equals`. Žádný test to ale neověřuje.

## Zkontrolováno bez nálezu
- **SQL:** nový dotaz v `OrderController.php:178` posílá hodnotu přes `$db->quote()`.
- **Změna stavu:** jde jen přes příkaz `CancelOrder` a doménu. Přechody hlídá `canTransitionTo()`, událost se zaznamená přes `record()`.
- **Sklad:** vrácení zboží zajišťuje existující `ReleaseReservationsHandler`, nový kód na to nepřibyl.
- **Migrace:** vypadá vygenerovaně a výchozí hodnota 0 nerozbije stávající SQL inserty staré administrace.
- **Reporty a export:** stornované objednávky už dnes nezapočítávají, nový sloupec je neovlivní.
- **Existující testy a konfigurace:** diff je nemění.