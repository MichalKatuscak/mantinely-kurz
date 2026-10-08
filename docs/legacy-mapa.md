# Legacy: mapa

Ověřeno proti kódu na tagu `m10-end`, čísla řádků znovu po přidání CSRF a kontroly rolí. Každé tvrzení má `soubor:řádek`.

## Report měsíčních tržeb
- Vstup: `src/Legacy/Http/LegacyFrontController.php` (stránka `report`) → `src/Legacy/Admin/ReportController.php:15` (`monthlyAction()`), měsíc z `$_GET['month']`, bez validace.
- Kontroler jen formátuje: hlavička `src/Legacy/lib/report.php:10` (`report_header()`, dnešní datum na řádku 14), řádky `src/Legacy/lib/report.php:22`.
- Výpočet: `src/Legacy/lib/report.php:27` volá `monthlyRevenue()` v `src/Legacy/lib/revenue.php:31`, ta deleguje na `src/Legacy/Report/MonthlyRevenue.php` (do modulu 10 počítala sama).
- Počítá jen objednávky ve stavu `paid` (`src/Legacy/lib/revenue.php:22`, `src/Legacy/Report/MonthlyRevenue.php`), odeslané a doručené ne. Storno v dalším měsíci objednávku z reportu zpětně vyřadí (filtr na aktuální stav).
- Sleva na objednávku (`orders.discount_amount_in_cents`) se **neodečítá**. Cizí měny přes `exchange_rates`.
- Stejné tržby jinak: dashboard `src/Legacy/Admin/AdminController.php:29`, export pro účetní `src/Legacy/lib/csv.php:61` (`exportRevenueSum()`: i shipped a delivered, sleva odečtená, měny bez převodu).

## Zápisy do tabulky objednávek
- `src/Legacy/Admin/order_edit.php:44`: změna stavu (`UPDATE orders SET status`), řádek 77 mění slevu.
- `src/Legacy/Admin/orders.php:30`: hromadné storno (řádky 39 a 45 hromadně zaplaceno a odesláno).
- `src/Legacy/cron.php:33`: rušení nezaplacených potvrzených objednávek starších 14 dní; rezervace ve skladu nechá viset.

Žádný z nich nejde přes agregát `Order`: nevzniká `OrderCancelled` a neplatí přechody `OrderStatus`.

## Závislosti
- Legacy → `src/Legacy/lib/db.php:65` (`legacy_db()`, globální `$db`), používá ho 42 souborů (`global $db`).
- Legacy → Ordering: jen příkazy přes `legacy_command()` (`src/Legacy/lib/functions.php:195`), storno `src/Legacy/Admin/OrderController.php:136` (`CancelOrder`) a změna množství `src/Legacy/Admin/OrderController.php:218` (`ChangeItemQuantity`). Hlídá Deptrac (vrstva `Legacy`).
- Ordering, Inventory → Legacy: žádné. Výjimka je protikorupční vrstva `src/Ordering/Infrastructure/Legacy/LegacyProductCatalog.php` (čte tabulku `products`), hlídá ji Deptrac (vrstvy `Legacy` a `LegacyAcl`).
