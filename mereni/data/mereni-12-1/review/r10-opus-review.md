## Review: storno ve staré administraci (`git diff m12-start HEAD`)

Testy jsem nepouštěl, protože tenhle checkout nemá nainstalovaný `vendor/`. Tvrzení autora „`make check` prošel, 124 testů“ proto neumím potvrdit. Všechno níže vychází z čtení kódu.

### Nálezy

**1. Storno zaplacené objednávky se slevou vyšší než součet položek skončí chybou 500 a objednávka nejde stornovat.**
`src/Ordering/Domain/Model/Order.php:270`
- `cancelAndRefund()` volá `paidAmount()`, tedy `totalAmount()->subtract($discount)`. `Money::__construct` (`src/SharedKernel/Domain/Money.php:18`) vyhodí `InvalidArgumentException('Money cannot be negative')`, když by vyšla záporná částka.
- Stará administrace takovou slevu dovoluje. `src/Legacy/Admin/order_edit.php:73-77` jen upozorní („sleva je vyšší než hodnota objednávky“) a slevu uloží.
- Detail objednávky ten případ řeší (`max(0, …)`, `src/Legacy/Admin/OrderController.php:92`), doména ne.
- `cancelAction()` chytá jen `InvalidOrderStateTransitionException` (`src/Legacy/Admin/OrderController.php:191`), takže výjimka propadne až k uživateli. Formulář se přitom pro `paid` zobrazuje.

**2. Ve stejné administraci zůstaly dvě jiné cesty ke stornu, které peníze nezaznamenají a zboží nevrátí.**
`src/Legacy/Admin/order_edit.php:44`, `src/Legacy/Admin/orders.php:30`
- Zadání chce storno, po kterém zákazník dostane peníze zpět a zboží se vrátí na sklad.
- Detail objednávky dál nabízí odkaz „Změnit stav / slevu“ (`src/Legacy/templates/orders/detail.php:72`). Tam jde zaplacenou objednávku přepnout na `cancelled` přes `UPDATE orders`, bez `OrderCancelled`, bez uvolnění rezervací a bez `refund_amount_in_cents`. Hromadné storno v `orders.php:30` dělá totéž.
- Výsledek: dvě cesty ke stejnému stavu `cancelled`, každá s jiným dopadem na sklad a na vratku.
- Autor to ve zprávě nezmiňuje. Je potřeba rozhodnout, jestli staré cesty zavřít, nebo je výslovně nechat mimo rozsah. Do `src/Legacy` se bez zadání nesahá, proto je to otázka na zadavatele, ne na tichou opravu.

**3. Když admin stornovanou objednávku obnoví, „K vrácení zákazníkovi“ zůstane.**
`src/Legacy/templates/orders/detail.php:57`, `src/Ordering/Domain/Model/Order.php:271`
- Admin smí stornovanou objednávku vrátit do jiného stavu (`order_edit.php:41-44`).
- `refund_amount_in_cents` se nikde nemaže. Řádek v šabloně se ukazuje jen podle toho, jestli sloupec není `null`, stav nehraje roli.
- Obnovená zaplacená objednávka tak dál hlásí částku, kterou má obchod ručně vrátit. Hrozí, že peníze vrátí zbytečně.

**4. Migrace nevznikla přes `make:migration` a důvod, který autor uvádí, nesedí s konfigurací.**
`migrations/Version20261008124535.php`
- `CLAUDE.md` povoluje migrace jen přes `bin/console make:migration`.
- Autor píše, že MakerBundle v projektu není. Přitom je v `composer.json:97` (`symfony/maker-bundle` v require-dev) i v `config/bundles.php:9` (`MakerBundle => ['dev' => true]`).
- Pokud v jeho prostředí chyběl, měl se podle pravidel zastavit a zeptat. Neměl obcházet generátor přes `doctrine:migrations:diff`.
- Samotné `up()` (jeden `ALTER TABLE … ADD COLUMN refund_amount_in_cents INTEGER DEFAULT NULL`) vypadá v pořádku. Na vývojové databázi ji autor podle vlastních slov nespouštěl.

**5. Zpráva autora nesedí se stavem repozitáře.**
`docs/pr.md`
- Píše: „Nic není commitnuté… změny zůstaly na větvi `beh`“. Změna je ale commit `1d58b58` na větvi `review`.
- Návrh tří commitů podle kroků plánu nebyl dodržen: všechno je v jednom commitu. `CLAUDE.md` přitom žádá commit a `make check` po každém kroku a autor sám přiznává, že `make check` pouštěl jen na celý stav.

### Ověřené otázky z `docs/review.md`, kde jsem chybu nenašel
- **CSRF a role:** `cancelAction()` volá `auth_require('obchod')` a `csrf_check()` dřív, než cokoli zapíše (`OrderController.php:172-175`). GET jen přesměruje. Obě kontroly opravdu něco kontrolují: `auth_require` odmítne nepřihlášeného (`src/Legacy/lib/auth.php:87`) a `csrf_check` porovnává token přes `hash_equals` (`src/Legacy/lib/csrf.php:45`). Formulář má `csrf_field()`.
- **SQL:** oba nové dotazy používají `$db->quote($id)` (`OrderController.php:178` a 196).
- **Stav přes doménu:** stav i vratka se mění jen přes `CancelOrder` → `Order::cancelAndRefund()` → `cancel()` (s `canTransitionTo()` a `record()`). Sklad uvolní existující `ReleaseReservationsHandler` po události `OrderCancelled`. Zápis SQL do `orders` ani `stock_items` v diffu není.
- **Existující kód:** `cancel()` se nezměnil a existující testy taky ne. `OrderRefundRecorded` bez handleru nevadí, protože `event.bus` má `allow_no_handlers: true` (`config/packages/messenger.yaml:9`).
- **Duplicitní výpočet:** vratka bere existující `Order::paidAmount()` (slovník: zaplacená částka), druhý výpočet v diffu není. Výjimkou je hraniční případ z nálezu 1.
- **Dopad na reporty:** co autor tvrdí o exportu, sedí. `exportOrders()` bere jen `paid/shipped/delivered` (`src/Legacy/lib/csv.php:49`), takže stornovaná objednávka z exportu vypadne, i zpětně za už exportovaný měsíc. Autor to uvádí.