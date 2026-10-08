# Review: storno ve staré administraci (`git diff m12-start HEAD`)

Celkově změna jde správnou cestou. Stav mění doména (`CancelOrder` → `Order::cancel()` → `OrderCancelled`), rezervace uvolní stávající `ReleaseReservationsHandler`, SQL hodnoty jdou přes `$db->quote()` a CSRF i role jsou ověřené. Našel jsem ale tyto chyby:

## Nálezy

**1. Storno zaplacené objednávky spadne, když je sleva vyšší než součet položek**
`src/Ordering/Application/Handler/CancelOrderHandler.php:28`
- Stará administrace takovou slevu výslovně povoluje, jen na ni upozorní (`src/Legacy/Admin/order_edit.php:69-73`).
- `paidAmount()` pak odečte víc, než je součet, a `Money` vyhodí `InvalidArgumentException('Money cannot be negative')` (`src/SharedKernel/Domain/Money.php:18-19`).
- Výjimku nikdo nechytá (`OrderController.php:191` chytá jen `InvalidOrderStateTransitionException`). Obsluha dostane chybu 500 a objednávka nejde stornovat vůbec.

**2. Dvojité odeslání formuláře zapíše vrácení peněz dvakrát**
`src/Ordering/Application/Handler/CancelOrderHandler.php:26-31` a `src/Legacy/Admin/OrderController.php:197-203`
- Dva souběžné POSTy (třeba dvojklik na „Stornovat“) oba načtou stav `Paid`, protože `Order` nemá zámek ani `#[ORM\Version]`.
- Oba proto vrátí plnou částku, vzniknou dvě poznámky „vrátit zákazníkovi …“ a dvakrát `OrderCancelled`.
- Tvrzení autora „nic se nevrací dvakrát“ platí jen pro storna po sobě, ne pro souběžná. Formulář nemá ani ochranu proti dvojímu odeslání.

**3. Poznámka o vrácení se zapisuje mimo transakci se stornem a nejde zopakovat**
`src/Legacy/Admin/OrderController.php:199-202`
- Storno se uloží a události se odešlou v handleru (`DoctrineOrderRepository::save`). Teprve potom stará administrace zvlášť udělá `INSERT INTO order_notes`.
- Když INSERT selže, objednávka už je stornovaná. Opakované storno vrátí 0 (`CancelOrderHandler.php:28`), takže informace o částce k vrácení se ztratí natrvalo a nikde jinde zapsaná není.

**4. Částka k vrácení se počítá z aktuální slevy, ne z toho, co zákazník opravdu zaplatil**
`src/Ordering/Application/Handler/CancelOrderHandler.php:28`
- `src/Legacy/Admin/order_edit.php:77` mění slevu v jakémkoli stavu, tedy i u zaplacené objednávky.
- Když obchod po zaplacení slevu změní, poznámka ukáže jinou částku, než zákazník poslal.
- Je to nejlepší zdroj, který aplikace má (platby nikde neeviduje). V PR by to ale mělo stát jako omezení, autor to neuvádí.

**5. Druhé vrácení peněz přes obnovu stornované objednávky**
`src/Legacy/Admin/order_edit.php:41-44` ve spojení s `CancelOrderHandler.php:28`
- Admin může stornovanou objednávku přes SQL vrátit na `paid`. Nové storno pak vrátí plnou částku znovu, ačkoli peníze už jednou odešly.
- Je to dřívější díra v Legacy, ale nové storno ji proměnilo ve druhou poznámku „vrátit zákazníkovi“. Stojí za zmínku v dopadech.

**6. Zpráva autora nesedí s tím, co je v repozitáři**
`docs/pr.md`, oddíl „Zpráva autora“
- Zpráva tvrdí, že autor nic necommitnul. Změna je přitom commit `6597b7a`.
- S tím souvisí i tvrzení, že Infection „proběhne při `make check` po commitu“. Zda opravdu proběhl, ze zprávy ověřit nejde.

## Co sedí
- **CSRF a role:** formulář má `csrf_field()`, akce volá `auth_require('obchod')` a pak `csrf_check()` dřív, než cokoli zapíše (`OrderController.php:171-175`). Obě kontroly opravdu něco kontrolují (`src/Legacy/lib/auth.php:85`, `src/Legacy/lib/csrf.php:42`). Bez POSTu akce jen přesměruje.
- **SQL:** všechny hodnoty jdou přes `$db->quote()` (`OrderController.php:178` a `199-202`).
- **Stav objednávky a skladu:** mění se jen přes doménu. Legacy sahá jen na příkaz, `OrderId` a doménovou výjimku, což `src/Ordering/CLAUDE.md` povoluje.
- **Bez nežádaných změn:** `Order`, `StockItem`, konfigurace ani existující testy se nezměnily.
- **Bez zdvojeného výpočtu:** částka se bere z `Order::paidAmount()`, žádný druhý výpočet.
- **Dopady na report, hromadné storno a cron** popsal autor pravdivě (sedí s `docs/legacy-mapa.md`).

## Co jsem neověřil
- Testy jsem nespustil, `vendor/bin/phpunit` vyžadoval povolení, které nebylo uděleno. Tvrzení „`make check` prošel, 124 testů“ je proto neověřené.
- Testy nepokrývají nálezy 1 až 3 ani odmítnutí uživatele bez role `obchod` (např. `sklad`).