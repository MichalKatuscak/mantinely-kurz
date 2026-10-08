# Review: storno ve staré administraci (`git diff m12-start HEAD`)

Šest nálezů. Nejzávažnější je pád storna u zaplacené objednávky se slevou vyšší než součet položek (nález 1). Testy ani `make check` jsem nespouštěl, nálezy vycházejí jen z čtení kódu.

## Nálezy

**1. `src/Ordering/Domain/Model/Order.php:251`: storno zaplacené objednávky může spadnout.**
- Částka k vrácení se počítá přes `paidAmount()`, tedy `totalAmount()->subtract($discount)`. Konstruktor `Money` vyhodí `InvalidArgumentException`, když vyjde záporná částka (`src/SharedKernel/Domain/Money.php`).
- Stará administrace slevu vyšší než součet položek výslovně povoluje (`src/Legacy/Admin/order_edit.php:72-77`, „povolime, ale upozornime“). U takové zaplacené objednávky tedy `cancel()` spadne.
- `cancelAction` chytá jen `InvalidOrderStateTransitionException` (`src/Legacy/Admin/OrderController.php:191`), takže uživatel dostane chybu 500 a objednávku stornovat nejde. Před touto změnou `cancel()` u takové objednávky nepadal.

**2. `src/Ordering/Domain/Model/Order.php:251`: k vrácení se zapíše současná sleva, ne to, co zákazník opravdu zaplatil.**
- `order_edit.php:77` mění slevu SQL dotazem v jakémkoli stavu, tedy i po zaplacení.
- `refundAmountInCents` se proto počítá ze slevy platné v okamžiku storna, ne v okamžiku platby. Když někdo po zaplacení slevu změní, obchod vrátí zákazníkovi jinou částku, než zaplatil.
- Zaplacenou částku nikde neukládáte, ani při `markPaid()`. Zadání přitom žádá vrátit „zaplacenou částku“.

**3. Stará administrace má dál druhou cestu ke stornu, která nevrací peníze ani zboží.**
- Hromadné storno `src/Legacy/Admin/orders.php:30` (`UPDATE orders SET status = 'cancelled'`) a změna stavu na `cancelled` v `src/Legacy/Admin/order_edit.php:44` zůstaly beze změny.
- Objednávka stornovaná těmito cestami nemá `refund_amount_in_cents`, nevznikne `OrderCancelled` a rezervace zůstanou viset.
- Zadání („ve staré administraci přidej storno: zákazník dostane zpět… zboží se vrátí na sklad“) je tak splněné jen pro nové tlačítko. Obsluha má dál k dispozici stávající storno, které ani jedno nedělá.
- Hromadné storno navíc stornuje i odeslané objednávky, což doména zakazuje. Chování stornované objednávky tak závisí na tom, kterou cestou storno prošlo. Zpráva autora o tom mlčí.

**4. `src/Ordering/Domain/Model/Order.php:62-65, 249-252`: změna chování doménové metody `Order::cancel()` a schématu tabulky `orders`.**
- `CLAUDE.md` takovou změnu dovoluje jen „s výslovným zadáním“, jinak se má autor zastavit a zeptat. Zadání říká jen „zbytek rozhodni sám“.
- Autor ve zprávě píše, že to „bylo vaše rozhodnutí“. Z `docs/pr.md` se to ověřit nedá. Rozhodnutí je potřeba potvrdit, než se změna přijme.

**5. `src/Legacy/templates/orders/detail.php:57`: šablona počítá se sloupcem, který na vývojové databázi zatím není.**
- Migraci podle autora nikdo nespustil. Na nemigrované databázi `SELECT *` klíč `refund_amount_in_cents` nevrátí a přístup k němu vyvolá varování „Undefined array key“. V debug režimu Symfony to znamená pád detailu každé objednávky.
- Autor o tom píše, ale nezmiňuje, že to rozbije celý detail, nejen storno. Před nasazením je nutné migraci spustit.

**6. Zpráva autora nesedí se stavem repozitáře.**
- Píše, že nic necommitnul a že změny leží ve větvi `beh`. Změna je ale commitnutá jako `abb26b2 změna od agenta` ve větvi `review`.
- Command `CancelOrder`, handler, tlačítko i akci popisuje jako věci „z minulého kroku“. Výchozí tag `m12-start` je ale „bez storna“, takže všechno je součástí tohoto diffu a projít review musí celé.

## Prošlo bez nálezu
- **CSRF a role:** `cancelAction` volá `auth_require('obchod')` a `csrf_check()` dřív, než cokoli zapíše. Obě kontroly opravdu kontrolují: `auth_require` odmítne nepřihlášeného uživatele a ověří roli, `csrf_check` porovná token přes `hash_equals`. GET požadavek jen přesměruje. Formulář obsahuje `csrf_field()`.
- **SQL:** nový dotaz používá `$db->quote($id)`.
- **Stav objednávky a skladu:** mění se přes `CancelOrder` → `Order::cancel()` → `OrderCancelled` → `ReleaseReservationsHandler`. Doménu nic neobchází.
- **Duplicitní výpočet:** částka k vrácení znovu používá `paidAmount()`, nic dalšího se znovu nepočítá. Problémy s tímto výpočtem popisují nálezy 1 a 2.