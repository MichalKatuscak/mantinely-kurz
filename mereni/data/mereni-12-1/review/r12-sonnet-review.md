## Review: storno ve staré administraci (`git diff m12-start HEAD`)

`make check` jsem nespustil, protože příkaz nebyl schválen. Tvrzení autora „120 testů, Infection 100 %“ jsem tedy neověřil. Všechny nálezy níže vycházejí z čtení kódu.

### Nálezy

**1. Storno zaplacené objednávky spadne, když je sleva vyšší než součet položek**
`src/Ordering/Domain/Model/Order.php:248` (`$this->refundDueInCents = $this->paidAmount()->amountInCents;`)
- Stará administrace dovoluje uložit slevu vyšší než součet položek, a to v jakémkoli stavu objednávky. Udělá jen varování: `src/Legacy/Admin/order_edit.php:72-77`.
- `paidAmount()` pak volá `Money::subtract()`. Ten vytvoří záporné `Money` a konstruktor vyhodí `InvalidArgumentException` (`src/SharedKernel/Domain/Money.php:18-19`).
- `cancelAction` zachytává jen `InvalidOrderStateTransitionException` (`src/Legacy/Admin/OrderController.php:190`). Výsledek je chyba 500 a takovou zaplacenou objednávku přes novou akci stornovat nejde.
- Detail objednávky přitom stejnou situaci řeší přes `max(0, …)` (`OrderController.php:92`).

**2. Částka k vrácení nemusí odpovídat tomu, co zákazník opravdu zaplatil**
`src/Ordering/Domain/Model/Order.php:248`
- Částka se počítá z aktuálních položek a aktuální slevy v okamžiku storna, ne z částky v okamžiku platby.
- Stará administrace mění slevu přímo přes SQL i po zaplacení (`order_edit.php:77`). Zapsané „Vrátit zákazníkovi ručně“ pak může být jiné než skutečně zaplacená částka.
- Zadání žádá vrátit „zaplacenou částku“. V PR to není uvedené jako omezení.

**3. Diff mění chování doménové metody `Order::cancel()` a schéma tabulky `orders` bez výslovného zadání**
`src/Ordering/Domain/Model/Order.php:62-64, 248-250`, `migrations/Version20261008124412.php:23`
- Podle `CLAUDE.md` nejde měnit chování `Order` bez výslovného zadání, a když úkol bez toho nejde, má se autor zastavit a zeptat.
- Zadání říká jen „stačí u objednávky zaznamenat“. Nový sloupec na agregátu a nový vedlejší efekt v `cancel()` jsou rozhodnutí autora.
- Ve zprávě to autor nepopisuje jako odchylku od pravidel, ke které chce souhlas. Mělo to být potvrzeno předem.

**4. Zpráva autora nesedí se stavem repozitáře**
`docs/pr.md`, poslední odstavec
- Autor píše, že jeho první změny commitnul někdo jiný jako „Výsledek běhu agenta“ (14bb347) a že migrace, změna `Order`, nový test a šablona jsou necommitované.
- Ve skutečnosti commit 14bb347 v historii není. Celá změna je v jediném commitu `c25a694` („změna od agenta“) a v pracovním stromu je necommitovaný jen `docs/pr.md`.
- Výčet „Co přibylo“ navíc nezmiňuje nové soubory `CancelOrder`, `CancelOrderHandler`, `cancelAction` ani routu `order_cancel` (`LegacyFrontController.php:112`). Test handleru `tests/Ordering/Application/CancelOrderHandlerTest.php` je jen okrajově v úvodu.

**5. Nová akce není otestovaná, autor to přiznává**
`src/Legacy/Admin/OrderController.php:167-201`, `src/Legacy/templates/orders/detail.php:59-73`
- Kontrolu role, CSRF, chybovou hlášku pro odeslanou objednávku i zobrazení částky žádný test nepokrývá.
- Ve spojení s nálezem 1 to znamená, že pád na nezachycené výjimce v akci nic neodhalí.

### Ověřeno bez nálezu
- **CSRF a role:** `cancelAction` volá `auth_require('obchod')` a `csrf_check()` dřív, než něco zapíše. Na GET jen přesměruje. Obě kontroly opravdu kontrolují: `auth.php:85-95` a `csrf.php:42-50`. Formulář má `csrf_field()` a metodu POST.
- **SQL:** nový dotaz v akci jde přes `$db->quote($id)` (`OrderController.php:176`). Akce na konci volá `detailAction()`, kde se `id` vlepuje do SQL bez ošetření (`OrderController.php:68-77`). To je ale starší kód mimo diff.
- **Stav objednávky a skladu:** mění se jen přes doménu, cestou `CancelOrder` → `Order::cancel()` → `OrderCancelled` → `ReleaseReservationsHandler`. Nic se nepočítá dvakrát: částka bere existující `paidAmount()`.
- **Existující testy a snapshoty:** nezměněné.
- **Přímé SQL storno jinde:** `orders.php`, `order_edit.php` a cron stornují dál přímo přes SQL, takže tam se částka k vrácení nezapíše a rezervace nechají viset. Autor to otevřeně uvádí jako zbývající práci.