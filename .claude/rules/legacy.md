---
paths:
  - "src/Legacy/**"
---
Stav objednávky a skladu jen přes doménu (command), žádné `UPDATE orders` ani SQL na `stock_items`.
src/Legacy bez zadání nerefaktorovat.
Stará administrace sdílí tabulky `orders` a `order_items` s kontextem Ordering a počítá
tržby po svém. Než ji změníte, ověřte dopad na report a export.
