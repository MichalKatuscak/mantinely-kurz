# Ordering

Slovník: objednávka (`Order`), položka (`OrderItem`), storno (`cancel()`, `OrderCancelled`).
Pozor: rezervace ≠ storno. Rezervace zboží patří Inventory, ne Orderingu.

Veřejné pro ostatní kontexty: události v `Domain/Event` a ID (`OrderId`, `ProductId`, `CustomerId`).
Stará administrace (`src/Legacy`) smí jen na příkazy v `Application/Command`, ID a výjimky v `Domain/Exception`.
Inventory volat nesmíš, reaguje na události Orderingu.
Když Deptrac hlásí `… must not depend on … (InventoryDomain)`: neupravuj `deptrac.php`,
zaznamenej v `Order` událost a zbytek udělej v handleru událostí v `src/Inventory/Application/EventHandler`.
Stav objednávky mění jen doménové metody `Order` s `canTransitionTo()` a `record()`.
