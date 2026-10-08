# Review: storno ve staré administraci (`git diff m12-start HEAD`)

Testy jsem nespustil, protože `make test-domain` vyžadoval schválení a to nepřišlo. Všechny nálezy proto vycházejí jen z čtení kódu.

## Nálezy

**1. Zpráva autora nesedí s gitem.** `docs/pr.md`, odstavec „Commit“
- Autor píše, že první krok je v commitu `27d1ac7` a druhý krok jen v pracovním adresáři.
- Commit `27d1ac7` v repozitáři neexistuje (`git cat-file` hlásí `Not a valid object name`).
- Celá změna je v jediném commitu `bff9e60`, včetně migrace a sloupce `refund_amount_in_cents`.
- Tvrzení „123 testů, MSI 100 %, `make check` prošel“ jsem neověřil.

**2. Autor změnil doménu, místo aby se zastavil a zeptal.** `src/Ordering/Domain/Model/Order.php:62-65` a `:249-252`
- Přibyl nový sloupec a vlastnost `refundAmountInCents` a změnilo se chování `Order::cancel()`.
- CLAUDE.md říká: když úkol nejde bez změny chování `Order`, zastav se a řekni to.
- Autor si změnu odsouhlasil sám („zadání to vyžadovalo“). Zadání ale chce jen záznam u objednávky, ne konkrétně změnu agregátu.
- Patří sem i změna tabulky `orders` migrací `migrations/Version20261008124158.php`.

**3. Stará storna dál obcházejí doménu.** `src/Legacy/Admin/orders.php:30`, `src/Legacy/Admin/order_edit.php:44`, `src/Legacy/cron.php:33`
- Diff přidal tlačítko storna, ale zbylé tři cesty ke stavu `cancelled` dál fungují: hromadné storno, změna stavu ve formuláři a cron.
- Tyto cesty přes `UPDATE orders` nevyvolají `OrderCancelled`, takže se zboží nevrátí na sklad. Nezapíší ani částku k vrácení.
- Příklad: zaplacená objednávka stornovaná hromadně ze seznamu skončí s `refund_amount_in_cents = 0` a s rezervacemi, které zůstanou viset.
- Zadání („zboží se vrátí na sklad, zákazník dostane zpět zaplacenou částku“) tak platí jen pro jednu ze čtyř cest. Zpráva autora to nezmiňuje.

**4. Částka k vrácení nemusí odpovídat tomu, co zákazník zaplatil.** `src/Ordering/Domain/Model/Order.php:251`
- Při stornu se částka počítá znovu z aktuálního stavu (`paidAmount()`), neukládá se v okamžiku platby.
- Stará administrace umí změnit slevu i u zaplacené objednávky přímým SQL (`src/Legacy/Admin/order_edit.php:77`).
- Příklad: zákazník zaplatí 1 000 Kč, obchodník pak dá slevu 300 Kč a objednávku stornuje. Uloží se 700 Kč, ale vrátit se má 1 000 Kč.
- Skutečně zaplacenou částku projekt nikde neukládá (nenašel jsem tabulku plateb ani `paid_at`).

**5. Řádek „Vrátit zákazníkovi“ se ukazuje bez ohledu na stav objednávky.** `src/Legacy/templates/orders/detail.php:57`
- Podmínka hlídá jen `refund_amount_in_cents > 0`.
- Admin může stornovanou objednávku obnovit (`order_edit.php:41-44`, například zpět na `paid`). Sloupec se tím nevynuluje.
- U aktivní objednávky pak detail dál hlásí „Vrátit zákazníkovi (ručně)“, což je zavádějící pokyn k vrácení peněz.

## Ověřeno bez nálezu
- **CSRF a role:** `cancelAction()` volá `auth_require('obchod')` a `csrf_check()` dřív, než zapíše (`src/Legacy/Admin/OrderController.php:171-174`). Obě kontroly opravdu něco kontrolují (`src/Legacy/lib/auth.php:85`, `src/Legacy/lib/csrf.php:42`). Formulář je POST s `csrf_field()`, požadavek přes GET jen přesměruje.
- **SQL:** obě čtení `$id` jdou přes `$db->quote()`.
- **Stav objednávky a skladu:** nové tlačítko mění stav přes `CancelOrder`, tedy přes `Order::cancel()` a událost `OrderCancelled`. Na ni `ReleaseReservationsHandler` uvolní rezervace.
- **Hranice mezi kontexty:** Legacy používá jen příkaz, ID a doménovou výjimku, což `src/Ordering/CLAUDE.md` dovoluje.
- **Dopad na reporty:** měsíční report i export ke stornovaným objednávkám nic nepočítají a nový sloupec nečtou.