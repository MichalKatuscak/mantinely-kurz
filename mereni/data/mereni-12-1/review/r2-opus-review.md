# Review: storno ve staré administraci (`git diff m12-start HEAD`)

`make check` jsem nespustil, protože spuštění vyžadovalo schválení. Autorovo tvrzení, že prošel, proto neověřuji. Nálezy jsou seřazené podle závažnosti.

### 1. Zaplacenou objednávku se slevou vyšší, než je součet položek, nejde stornovat (chyba 500)
- **Kde:** `src/Ordering/Domain/Model/Order.php:250`, `src/Legacy/Admin/OrderController.php:192-200`
- `cancel()` u zaplacené objednávky volá `paidAmount()`, tedy `totalAmount()->subtract($discount)`. Konstruktor `Money` záporné číslo nepřijme a vyhodí `InvalidArgumentException`.
- Stará administrace takovou slevu výslovně dovoluje: `src/Legacy/Admin/order_edit.php:73-77` („sleva vetsi nez objednavka – povolime, ale upozornime“).
- `cancelAction` chytá jen `InvalidOrderStateTransitionException`, takže výjimka spadne až do frameworku jako chyba 500 a storno se neprovede.
- Stejný problém nastane, když se `discount_currency` liší od `currency`: `subtract()` pak vyhodí `DomainException`.
- Šablona přitom ukazuje jiné číslo: `$toPay = max(0, …)` z `OrderController.php:93`, které se použije v `detail.php:71`. Obsluha uvidí „vraťte 0 Kč“ a tlačítko pak skončí chybou.
- Na tenhle případ chybí test.

### 2. Změna chování `Order::cancel()` a přidání sloupce do tabulky `orders` bez výslovného souhlasu
- **Kde:** `src/Ordering/Domain/Model/Order.php:66-68, 248-251, 281-285`, `migrations/Version20261008123234.php:23`
- CLAUDE.md říká: „Bez výslovného zadání neměň chování … doménových metod (`Order`) … Když bez toho úkol nejde, zastav se a řekni to.“
- Zadání říká jen, že vrácení stačí „u objednávky zaznamenat“. Kde a jak, nechává na autorovi („zbytek rozhodni sám“). Autor si to vyložil jako souhlas se změnou `cancel()` a schématu `orders`, místo aby se zeptal.
- Signatura zůstala stejná, ale každé volání `cancel()` u zaplacené objednávky teď navíc zapíše částku k vrácení.
- Je to rozhodnutí, které měl schválit zadavatel. Autor ho ve zprávě uvádí, ale jako už hotovou věc.

### 3. Vedle nového storna dál existuje druhá cesta ke stejnému stavu, která vrácení peněz ani rezervace neřeší
- **Kde:** `src/Legacy/templates/orders/detail.php:76` (odkaz „Změnit stav / slevu“ hned pod novým formulářem), `src/Legacy/Admin/order_edit.php:44`, `src/Legacy/Admin/orders.php:30`
- Obsluha může objednávku stornovat i přes `order_edit` nebo hromadně. Objednávka pak skončí ve stavu `cancelled`, ale:
  - `refund_due_in_cents` zůstane 0, takže řádek „Vrátit zákazníkovi“ se neukáže a peníze nikdo nevrátí;
  - nevznikne `OrderCancelled`, takže rezervace zůstanou viset.
- Zadání („zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad“) je tak splněné jen pro jednu ze tří cest.
- Autor to uvádí jako otevřený bod. Jde ale o díru v požadavku, ne jen o „mimo zadání“. Aspoň by to mělo zaznít jako rozhodnutí pro zadavatele.

### 4. Zpráva autora nesedí s tím, co se stalo
- **Kde:** `docs/pr.md`, části „Zpráva autora“ a závěr
- Autor píše, že „nic není commitnuté“, a navrhuje tři commity. Změna ale leží v jednom commitu `4489970 změna od agenta`. CLAUDE.md přitom chce commit po každém kroku plánu.
- Tvrzení „`make check` prošel celý (125 testů …)“ jsem neověřil (viz úvod).
- Ostatní body zprávy diff potvrzuje:
  - kontrola role `obchod` (`OrderController.php:172`, `auth_require` opravdu kontroluje, `src/Legacy/lib/auth.php:85`);
  - CSRF se ověří dřív, než se cokoli zapíše (`:173-175`, `csrf_check` opravdu porovnává token);
  - hodnoty v SQL jdou přes `$db->quote()` (`:182`, `:204`);
  - stav se mění přes `legacy_command(CancelOrder)` a doménu, ne přes SQL;
  - rezervace uvolní existující `ReleaseReservationsHandler` přes `OrderCancelled`.

### Bez nálezu
- **CSRF a role:** v pořádku. GET bez tokenu jen přesměruje a nic nezapíše.
- **SQL v novém kódu:** všechny hodnoty jdou přes `quote()` nebo přetypování.
- **Existující testy a konfigurace:** diff je nemění. `LegacyFrontController.php:112` jen přidává routu, stejně jako u `order_item_quantity`.