# Review: storno objednávky ve staré administraci (`m12-start..HEAD`)

Celkově: změnu nedoporučuju přijmout. Storno se z administrace nedá spustit a vrácení peněz se nikde nezaznamená, takže zadání není splněné.

## Nálezy

### 1. Storno se z administrace nedá spustit (blokující)
**`src/Legacy/templates/orders/detail.php:63`, `src/Legacy/Http/LegacyFrontController.php:55–112`**
Odkaz „Stornovat“ vede na stránku `order_cancel`. Ta ale v `LegacyFrontController::$pages` není a diff tenhle soubor vůbec nemění. Na neznámou stránku vrátí `__invoke()` rovnou 404 (`LegacyFrontController.php:118–119`), takže `cancelAction()` se nikdy nezavolá. Autorovy body 1–5 („obchodník klikne…“) proto v aplikaci nenastanou.

### 2. Vrácení zaplacené částky se nikde nezaznamená (požadavek zadání chybí)
**`src/Ordering/Application/Handler/CancelOrderHandler.php:21`, `src/Ordering/Domain/Event/OrderCancelled.php:12–17`**
Zadání chce vrácení peněz u objednávky zaznamenat. Diff ale ukládá jen stav `cancelled` a důvod (`cancellationNote`). `OrderCancelled` nese `orderId`, `customerId`, `reason` a `occurredAt`, žádnou částku ani informaci, že se má něco vracet. Obchod proto nemá podle čeho peníze ručně vrátit. Tvrzení autora „Vrácení peněz se zaznamená v eventu“ (bod 6) neodpovídá kódu.

### 3. Vrácení peněz se slibuje i u nezaplacené objednávky
**`src/Legacy/Admin/OrderController.php:193`, `src/Legacy/templates/orders/cancel.php:10` a `:16`**
Storno je povolené pro `draft` a `confirmed` (`detail.php:62`, `OrderStatus.php:24–25`), kde zákazník nic nezaplatil. Hláška i šablona přesto vždy tvrdí, že zákazník dostane zaplacenou částku zpět, a ukazují ji jako „K úhradě“. Akce nerozlišuje, jestli objednávka byla zaplacená. Obchodník tak může vrátit peníze, které nikdy nepřišly.

To samé platí pro objednávku, která už stornovaná je: `Order::cancel()` je pro `Cancelled` bez chyby (`Order.php:231–233`), takže se znovu zobrazí „stornována, zákazníkovi bude vrácena…“ a hrozí dvojí ruční vrácení.

### 4. Zaplacená částka se počítá podruhé, mimo doménu
**`src/Legacy/Admin/OrderController.php:199–200`**
`$sum` a `$toPay` jsou SQL kopie výpočtu z `detailAction()` (`OrderController.php:79–92`). Doména přitom zaplacenou částku má: `Order::paidAmount()` (`Order.php:266`, ve slovníku v CLAUDE.md jako „zaplacená částka“). Je to druhá cesta ke stejné hodnotě. Částka, kterou formulář ukazuje, se nikam nepředává ani neukládá, takže nález 2 neřeší.

### 5. Závěrečná zpráva autora nesedí s diffem
**`docs/pr.md`**
- Uvádí „Commit c75bec8“, změna je ale v `a8c00a6`.
- „Vrácení peněz se zaznamená v eventu“ neplatí (nález 2).
- „Storno je integrované“ neplatí, akce není dostupná (nález 1).
- „`make test-domain` ✓ (36 testů)“ jsem neověřil, spuštění příkazu nebylo povolené. Diff ale nepřidává žádný test na `CancelOrderHandler` ani na `cancelAction`, takže storno testy neověřují.

## Kontrolní otázky z `docs/review.md`
- **CSRF a role:** v pořádku. Akce volá `auth_require('obchod')` (`OrderController.php:168`) a před zápisem `csrf_check()` (`:170`), formulář má `csrf_field()` (`cancel.php:19`). Obě kontroly opravdu něco kontrolují (`auth.php:85–95`, `csrf.php:42–50`). Na GET se nic nezapisuje.
- **SQL:** všechny nové dotazy jdou přes `$db->quote()` (`OrderController.php:174`, `:198`, `:199`).
- **Stav mimo doménu:** ne. Stav mění `Order::cancel()` přes command bus a sklad uvolňuje existující `ReleaseReservationsHandler` po `OrderCancelled`, události se publikují v `DoctrineOrderRepository::save()`.
- **Změny existujícího kódu bez zadání:** signatury doménových metod, konfigurace ani testy se nemění. `detail.php` dostal jen nový odkaz.