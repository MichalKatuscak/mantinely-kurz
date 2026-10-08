Prošel jsem celý diff `m12-start..HEAD` a souvislosti v kódu. Storno samo jde přes doménu: přes příkaz `CancelOrder` a událost `OrderCancelled`. Akce má `auth_require('obchod')` i `csrf_check()` a obě kontroly opravdu něco kontrolují (`src/Legacy/lib/auth.php:85`, `src/Legacy/lib/csrf.php:42`). Hodnota do SQL jde přes `$db->quote()`. Mám ale tyto nálezy:

## Nálezy

**1. Storno zaplacené objednávky spadne na výjimku, když je sleva vyšší než součet položek** (`src/Ordering/Domain/Model/Order.php:254-256`, `src/Legacy/Admin/OrderController.php:190`)
`cancel()` u zaplacené objednávky nově volá `paidAmount()`. Stará administrace v `src/Legacy/Admin/order_edit.php:73-77` slevu vyšší než objednávka výslovně povoluje, jen upozorní. `Money::subtract()` pak vytvoří zápornou částku a konstruktor `Money` vyhodí `InvalidArgumentException` (`src/SharedKernel/Domain/Money.php:18`). `cancelAction()` chytá jen `InvalidOrderStateTransitionException`, takže uživatel dostane chybu 500 a objednávku nestornuje. Před změnou by `cancel()` na takové objednávce prošel.

**2. Částka k vrácení se počítá z dnešní slevy, ne z toho, co zákazník zaplatil** (`src/Ordering/Domain/Model/Order.php:255`)
Zaplacená částka se v okamžiku platby nikam neukládá. `order_edit.php:67-80` dovolí změnit slevu i u zaplacené objednávky, protože u slevy stav nehlídá. Když obchodník po zaplacení slevu změní, uloží se do `refund_in_cents` jiná částka, než kterou zákazník zaplatil. Zadání přitom chce vrátit „zaplacenou částku“.

**3. Stará cesta ke stornu zůstala a zadání nesplňuje** (`src/Legacy/Admin/order_edit.php:44`, `src/Legacy/Admin/orders.php:30`)
Diff přidává druhou cestu ke stejnému stavu. Odkaz „Změnit stav / slevu“ v detailu (`src/Legacy/templates/orders/detail.php:63`) dál vede na `order_edit.php`, kde jde stav nastavit na `cancelled` rovnou přes SQL. Stejně tak hromadné storno v `orders.php`. Ani jedna z těchto cest nezaznamená částku k vrácení a nevrátí zboží na sklad (`order_edit.php:60` má na to jen TODO). Zadání „u objednávky storno: zákazník dostane zpět peníze a zboží se vrátí na sklad“ je tak splněné jen pro novou akci. Autor to má ve zprávě jako „mimo rozsah“, ale je to tatáž funkce na téže obrazovce. O rozsahu by měl rozhodnout zadavatel.

**4. Změna doménové metody `Order::cancel()` bez výslovného zadání** (`src/Ordering/Domain/Model/Order.php:62-69`, `:253-256`)
CLAUDE.md zakazuje měnit chování doménových metod `Order` bez výslovného zadání. Zadání říká jen „vrácení peněz stačí u objednávky zaznamenat“, kam a jak záznam uložit neurčuje. Autor do agregátu přidal nový stav a nový sloupec a postavil na tom i migraci. O tom by měl rozhodnout zadavatel, ne autor sám. Řešení přes událost nebo samostatný záznam by `Order` neměnilo.

**5. Neověřený dopad na report tržeb a export** (`src/Legacy/lib/revenue.php:22`, `src/Legacy/lib/csv.php:61`)
`.claude/rules/legacy.md` vyžaduje před změnou ověřit dopad na report a export. Měsíční report počítá jen objednávky ve stavu `paid` a filtruje podle aktuálního stavu. Když se zaplacená objednávka stornuje, zmizí zpětně z tržeb už uzavřeného měsíce, a to i z exportu pro účetní. Tohle chování platilo už dřív, ale teprve tato změna dělá ze storna zaplacené objednávky běžnou akci. Vratka se přitom neobjeví v žádném reportu ani exportu. Zpráva autora tenhle dopad vůbec nezmiňuje.

**6. Zpráva autora nesedí s diffem** (`docs/pr.md`)
- Zpráva tvrdí, že commit neproběhl a změny jsou jen v pracovním adresáři. Ve skutečnosti jsou zacommitované (`b8ac36c`).
- Sekce „Co se změnilo“ vynechává podstatnou část diffu: nový příkaz `CancelOrder` a `CancelOrderHandler` (`src/Ordering/Application/...`), akci `cancelAction()` (`src/Legacy/Admin/OrderController.php:167`), routu `order_cancel` (`src/Legacy/Http/LegacyFrontController.php:112`), formulář pro storno (`detail.php:67-73`) a test `tests/Ordering/Application/CancelOrderHandlerTest.php`.
- Zpráva tvrdí, že autor do `tests/Acceptance/Legacy/CancelOrderTest.php` kontroly jen „doplnil“. Vůči `m12-start` je to ale celý nový soubor.

## Co jsem neověřil
- `make check` jsem nespustil, prostředí ho bez schválení nepovolilo. Tvrzení autora o 122 testech, 100 % zabitých mutantech a čistém PHPStanu a Deptracu jsou proto neověřená.
- Nález 1 jsem neprověřil testem. Opírá se o kód `order_edit.php:73-77`, `Money.php:18` a o to, co chytá `cancelAction()`.