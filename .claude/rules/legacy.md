---
paths:
  - "src/Legacy/**"
---
Stav objednávky a skladu jen přes doménu (command), žádné `UPDATE orders` ani SQL na `stock_items`.
Hodnota do SQL jen přes `$db->quote()` nebo přetypování (`(int)`, `ids_to_sql()`). Parametr z požadavku (`get_param()`, `post_param()`, `$_POST`) do dotazu nevlepovat.
Akce, která mění data: POST formulář s `csrf_field()`; akce hned na začátku volá `auth_require('<role>')` (role `admin`, `obchod`, `sklad`, `ucetni`; admin smí vše) a `csrf_check()`. Odkazy přes GET, které mění data (`product_toggle`, `invoice_issue`), jsou dluh, ne vzor.
Kontrola: `make phpstan-legacy` (zápis do `orders`/`stock_items` mimo doménu, hodnota vlepená do SQL); po každé editaci ji spouští `make check-changed`. Baseline `phpstan-legacy-baseline.neon` negenerovat znovu.
src/Legacy bez zadání nerefaktorovat.
Stará administrace sdílí tabulky `orders` a `order_items` s kontextem Ordering a počítá
tržby po svém. Než ji změníte, ověřte dopad na report a export.
