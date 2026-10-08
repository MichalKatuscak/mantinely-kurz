Hotovo, sepisuji nálezy.

# Review: storno objednávky ve staré administraci (`git diff m12-start HEAD`)

Prošel jsem diff, `src/Ordering/CLAUDE.md`, kód, který diff volá (`Money`, `OrderStatus`, `legacy_command`, `csrf_check`, `auth_require`, `order_edit.php`, handlery v Inventory), a akceptační test. Testy ani `make check` jsem nespouštěl. Všechno níže je ze čtení kódu.

## Nálezy

### 1. Zaplacenou objednávku se slevou vyšší než součet položek už nejde stornovat
**`src/Ordering/Domain/Model/Order.php:249-251`**
- `cancel()` teď u stavu `paid` volá `paidAmount()`, tedy `totalAmount()->subtract($this->discount)`.
- Stará administrace ale vyšší slevu než součet položek povoluje a uloží ji. Jen u toho vypíše upozornění „sleva je vyšší než hodnota objednávky“ (`src/Legacy/Admin/order_edit.php:69-72`).
- Pak by výsledek odečtení byl záporný. Konstruktor `Money` ale zápornou částku odmítne a vyhodí `InvalidArgumentException` (`src/SharedKernel/Domain/Money.php:18-20`).
- Výsledek: `cancel()` spadne dřív, než změní stav. Před touto změnou šla taková objednávka stornovat bez problémů, jde tedy o regresi existující doménové metody.
- `cancelAction` (`src/Legacy/Admin/OrderController.php:190`) chytá jen `InvalidOrderStateTransitionException`, takže uživatel dostane chybu 500 místo hlášky.
- Stejně dopadne každé storno zaplacené objednávky odjinud, třeba přes nový `CancelOrderHandler`, protože logika je v doméně.

### 2. Bez zadání se mění chování a data doménové metody `Order::cancel()`
**`src/Ordering/Domain/Model/Order.php:62-65, 249-251, 281-285`**
- `CLAUDE.md` říká: „Bez výslovného zadání neměň chování … doménových: `Order` … Když bez toho úkol nejde, zastav se a řekni to.“
- Zadání chtělo storno ve staré administraci a záznam vrácené částky. Nežádalo, aby se `cancel()` měnila pro všechna storna v aplikaci, ani nový sloupec v tabulce `orders`.
- Autor to rozhodl sám („Patří to do domény, takže … u každého storna“) a nezastavil se. Nález 1 je přímý důsledek.
- Pravidlo „zbytek rozhodni sám“ ze zadání toto výslovné omezení podle mě neruší.

### 3. Migrace nevznikla přes `make:migration`
**`migrations/Version20261008123827.php`**
- `CLAUDE.md` požaduje jen `bin/console make:migration`. Autor použil `doctrine:migrations:diff --env=test`.
- Autor to přiznává. Je to ale porušení pravidla, ne jen „odchylka“, a mělo se řešit dotazem před implementací.
- Obsah migrace (jeden nullable sloupec) jinak sedí s mapováním.

### 4. Druhá cesta ke stejnému stavu zůstává hned vedle nového tlačítka
**`src/Legacy/Admin/order_edit.php:44`, odkaz v `src/Legacy/templates/orders/detail.php:70`**
- Na stejné stránce detailu je dál odkaz „Změnit stav / slevu“. Tam jde nastavit stav `cancelled` přímo SQL příkazem.
- Takové storno nezaznamená částku k vrácení, nevyvolá `OrderCancelled`, a proto neuvolní rezervace (viz TODO v `order_edit.php:58`).
- Obsluha tak má dvě cesty ke „stornu“ a jen jedna z nich splní zadání: vrácení peněz i vrácení zboží na sklad.
- Autor zmiňuje jen `orders.php` a `cron.php`, tuto cestu ze stejné obrazovky vynechal.

### 5. Admin může stornovanou objednávku vrátit SQL příkazem a zůstane jí viset částka k vrácení
**`src/Legacy/templates/orders/detail.php:57-59`, `src/Legacy/Admin/order_edit.php:41-44`**
- Admin smí stornovanou objednávku obnovit, třeba zpátky na `paid`. SQL ale sloupec `refund_amount_in_cents` nevynuluje.
- Detail pak u zaplacené, nestornované objednávky ukazuje „K vrácení zákazníkovi“.
- To odporuje autorově tvrzení, že `refundAmount()` vrací u nestornované objednávky `null`.

### 6. Zpráva autora neodpovídá stavu repozitáře
**`docs/pr.md`**
- Zpráva tvrdí: „**nic není commitnuté**“. Změna je přitom commitnutá jako `c4c629b změna od agenta`.
- Tvrzení „Signatura `cancel()` ani události se nezměnily“ je pravdivé. Zamlčuje ale, že se změnilo chování `cancel()` (nálezy 1 a 2).

## Ověřeno bez nálezu
- **CSRF a role:** formulář posílá POST s `csrf_field()`. `cancelAction` volá `auth_require('obchod')` i `csrf_check()` dřív, než pošle příkaz. Obě kontroly opravdu něco kontrolují: `csrf_check` porovnává token přes `hash_equals`, `auth_require` ověřuje roli. Požadavek GET nic nezapíše.
- **SQL:** jediný nový dotaz používá `$db->quote($id)`.
- **Stav objednávky a skladu:** mění se jen přes `CancelOrder` → `Order::cancel()` → `OrderCancelled` → `ReleaseReservationsHandler`. Tvrzení, že se zásoba `onHand` nikdy nesnižuje a stačí uvolnit rezervace, odpovídá kódu Inventory.
- **Výjimky z Messengeru:** `legacy_command` rozbalí `HandlerFailedException`, takže `catch` na `InvalidOrderStateTransitionException` funguje.
- **Závislosti Legacy → Ordering:** jen příkaz, ID a výjimka z `Domain/Exception`, což `src/Ordering/CLAUDE.md` povoluje.
- **Existující testy, konfigurace, snapshoty:** diff je nemění. Žádný jiný `SELECT * FROM orders` nevypisuje všechny sloupce, takže nový sloupec neovlivní výstupy reportu ani exportu.

## Co by mělo přibýt do testů
Doménový nebo akceptační test, který stornuje zaplacenou objednávku se slevou vyšší než součet položek. Odhalil by nález 1.