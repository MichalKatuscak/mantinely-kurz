# Plán: změna množství položky ve staré administraci

**Tiket:** Ve staré administraci (src/Legacy) přidej v detailu objednávky změnu množství
položky. Jde jen u rozpracované objednávky.

## Rozhodnutí
- Objednávka se mění přes doménu: akce staré administrace pošle příkaz `ChangeItemQuantity`
  na command bus a handler zavolá novou metodu `Order::changeItemQuantity()`. Žádné SQL na
  `orders` ani `order_items`, i když `order_edit.php` a hromadné storno v `orders.php` mění
  objednávky přímým `UPDATE`.
- Pravidla hlídá agregát `Order` (stav, kladné množství, položka v objednávce), ne akce ve
  staré administraci.
- Stará administrace na command bus zatím nevidí. Most: `LegacyFrontController` dostane
  command bus a funkce `legacy_command()` v `lib/functions.php` přes něj pošle příkaz.
  Doménovou výjimku vrátí staré administraci rozbalenou, ne v obálce Messengeru.
- Novou částku po změně počítá `Order` (`totalAmount()`, `paidAmount()`), stará administrace
  ji jen zobrazí.

## Akceptační kritéria
1. Množství jde změnit jen u rozpracované objednávky (Draft). U jiné skončí změna výjimkou
   `InvalidOrderStateTransitionException`, stará administrace ukáže chybu a množství zůstane.
2. Množství musí být kladné, jinak `InvalidQuantityException` a stará administrace ukáže chybu.
3. Změna jde přes doménu (příkaz a metoda `Order`), ne přes SQL.

## Mimo rozsah
- Úprava rezervace ve skladu po změně množství (kontext Inventory).
- Změny existujících metod `Order` (přibude jen nová metoda).
- Ochrana akcí staré administrace (kontrola role, token proti CSRF).

## Otevřená otázka
Kdo smí množství měnit?

**Rozhodnutí:** obsluha s rolí `obchod` (a `admin`), stejně jako ostatní změny objednávky.
Stará administrace roli zatím nekontroluje (`auth_require()` je vypnutá), takže kód tohle
rozhodnutí dnes nevynutí. Akce je do té doby chráněná jako ostatní akce staré administrace,
jen přihlášením do administrace.

## Kroky (po každém commit, před commitem `make check`)
1. Akceptační test kritéria 1 přes akci staré administrace
   (`tests/Acceptance/Legacy/ChangeItemQuantityTest.php`): změna u potvrzené objednávky
   skončí chybou a množství zůstane. Červený.
2. `Order::changeItemQuantity()` a `OrderItem::changeQuantity()` s testy agregátu
   (kritéria 1 a 2).
3. Příkaz `ChangeItemQuantity` a `ChangeItemQuantityHandler`.
4. Akce `order_item_quantity` ve staré administraci (přes most na command bus) a formulář
   v detailu objednávky.
