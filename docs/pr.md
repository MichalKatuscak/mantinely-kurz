# Popis změny (PR)

## Zadání
Ve staré administraci (src/Legacy) přidej u objednávky storno: zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad.

## Zpráva autora
Storno je v detailu objednávky ve staré administraci hotové. Všechny testy prošly (`make check`: 31 testů OK).

**Jak to funguje**

V detailu objednávky (`/admin/legacy/order?id=…`) je nové tlačítko „Stornovat objednávku“ s nepovinným důvodem. Ukazuje se jen u objednávek ve stavu rozpracovaná, potvrzená a zaplacená.

O stornu rozhoduje doména `Order::cancel()`, stará administrace jen předá příkaz:
- **Vrácení peněz:** částku k vrácení spočítá `Order::cancel()` z `paidAmount()`, tedy po slevě. Stará administrace ji znovu nepočítá. Nezaplacená objednávka nevrací nic. Částka je nově i v události `OrderCancelled` (pole `refund`).
- **Zboží na sklad:** na `OrderCancelled` reaguje už existující `ReleaseReservationsHandler` a uvolní rezervace.
- **V administraci:** vznikne záznam v historii, poznámka „Storno: vrátit zákazníkovi 850,00 Kč“ a e-mail zákazníkovi, pokud je v tabulce `customers`.

**Co je kde**
- Nový příkaz `CancelOrder` a handler `CancelOrderHandler`, který vrací částku k vrácení.
- `LegacyFrontController` je teď služba s command busem a staré administraci ho zpřístupní přes funkci `legacy_command()`. Na Symfony tak dál závisí jen tento most.
- Akce je `OrderController::cancelAction` (stránka `order_cancel`).
- Testy: tři nové doménové v `OrderTest` a `tests/Legacy/OrderCancelTest.php`, který přes HTTP prověří zaplacenou objednávku se slevou (vratka, stav, sklad) a odmítnutí odeslané objednávky.

**Na co si dát pozor**
- **Peníze se fyzicky neposílají.** Aplikace nemá žádné napojení na platby, takže vratka je jen poznámka, záznam v historii a e-mail. Převod musí udělat člověk.
- **Odeslanou objednávku nejde stornovat**, protože to doména nepovoluje. Hromadné storno v `orders.php` to dosud umožňovalo.
- **Dvě staré cesty storno obcházejí:** hromadné storno v `orders.php` a ruční změna stavu na „Stornovaná“ v `order_edit.php`. Obě jen přepíšou stav, bez vratky a bez uvolnění skladu. Nechal jsem je beze změny; můžu je převést na stejný příkaz.
- **Sleva vyšší než hodnota objednávky:** `order_edit.php` ji dovolí uložit. U takové zaplacené objednávky by `paidAmount()` spadl na záporné částce a storno skončí chybou. Tuhle nekonzistenci jsem neřešil.
- Formulář nemá CSRF ochranu, stejně jako zbytek staré administrace.

Nic jsem necommitoval.

## Změna

Změna je `git diff m07-end m08-start` (soubor `docs/pr.md` není její součástí). Most ze staré administrace na command bus (`legacy_command()`, `LegacyFrontController`) je v aplikaci od `m03-end`, v diffu proto není. Po běhu agenta jsou tři dotazy v `OrderController::cancelAction()` ošetřené přes `$db->quote()` podle hlášky `make phpstan-legacy`, jinak je kód z běhu beze změny.
