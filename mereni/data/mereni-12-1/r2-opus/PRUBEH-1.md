model: claude-opus-5-5  nástroj: Claude Code 2.1.291
[1] Skill: {"skill": "novy-pripad-uziti", "args": "Ve staré administraci (src/Legacy) přidej u objednávky storno: zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad."}
   → Launching skill: novy-pripad-uziti
[2] Bash: git ls-files | grep -v '^vendor/' | head -200 && cat src/Ordering/Domain/Order.php src/Ordering/Domain/OrderStatus.php 2>/dev/null
   → Exit code 1 .claude/hooks/existujici-testy.php .claude/rules/legacy.md .claude/settings.json .claude/skills/novy-pripad-uziti/SKILL.md .codex/hooks.json .cursor/hooks.json .cursor/hooks/check-changed.php .editorconfig .e
[3] Bash: git ls-files | grep -v '^vendor/' | tail -n +200; cat src/Ordering/CLAUDE.md .claude/rules/legacy.md src/Ordering/Domain/Model/Order.php src/Ordering/Domain/ValueObject/OrderStatus.php src/Ordering/Do
   → src/Ordering/Infrastructure/Doctrine/Type/CustomerIdType.php src/Ordering/Infrastructure/Doctrine/Type/OrderIdType.php src/Ordering/Infrastructure/Doctrine/Type/ProductIdType.php src/Ordering/Infrastructure/Export/Invoic
[4] Bash: sed -n 1,400p src/Legacy/Admin/OrderController.php; cat src/Legacy/templates/orders/detail.php; sed -n 180,230p src/Legacy/lib/functions.php; cat src/Ordering/Application/Command/ChangeItemQuantity.ph
   → <?php /**  * Objednavky – seznam a detail.  *  * Editace stavu a slevy je v order_edit.php, hromadne storno v orders.php  * (stare stranky, nikdo je neprepsal).  */  namespace App\Legacy\Admin;  class OrderController ext
[5] Grep: (?i)refund|vracen|cancel|PAYMENT_API|storno
   → docs/legacy-mapa.md:9:- Počítá jen objednávky ve stavu `paid` (`src/Legacy/lib/revenue.php:22`, `src/Legacy/Report/MonthlyRevenue.php`), odeslané a doručené ne. Storno v dalším měsíci objednávku z reportu zpětně vyřadí (
[6] Bash: cat src/Inventory/Domain/Model/StockItem.php src/Inventory/Application/EventHandler/ReserveStockHandler.php src/Ordering/Application/Handler/PayOrderHandler.php src/Ordering/Application/Command/PayOrd
   → <?php  declare(strict_types=1);  namespace App\Inventory\Domain\Model;  use App\Inventory\Domain\Exception\InsufficientStockException; use App\Inventory\Domain\Exception\NothingReservedException; use App\Inventory\Domain
TEXT: Zjistil jsem, co už existuje a co chybí. Ještě ověřím, jak se doménové události dostanou na event bus.
[7] Bash: cat src/Ordering/Infrastructure/Repository/DoctrineOrderRepository.php | sed -n 1,80p; cat config/packages/messenger.yaml; grep -rn -i "payment\|platb" src --include=*.php | grep -v "^src/Legacy" | he
   → <?php  declare(strict_types=1);  namespace App\Ordering\Infrastructure\Repository;  use App\Ordering\Domain\Exception\OrderNotFoundException; use App\Ordering\Domain\Model\Order; use App\Ordering\Domain\Repository\OrderR
TEXT: Zastavil jsem se dřív, než jsem cokoli změnil. Storno se zbožím zpátky na skladě udělat jde, ale vrácení peněz v kódu nemá na čem stát. Zatím jsem nic neupravil ani necommitnul.  ## Co už v kódu je - **Storno v doméně:** `Order::cancel(string $reason, \DateTimeImmutable $when)` (`src/Ordering/Domain/Model/Order.php:228`) projde přechody `OrderStatus` (stornovat jde rozpracovanou, potvrzenou a zapl

VÝSLEDEK (9 kroků, 42 s):
Zastavil jsem se dřív, než jsem cokoli změnil. Storno se zbožím zpátky na skladě udělat jde, ale vrácení peněz v kódu nemá na čem stát. Zatím jsem nic neupravil ani necommitnul.

## Co už v kódu je
- **Storno v doméně:** `Order::cancel(string $reason, \DateTimeImmutable $when)` (`src/Ordering/Domain/Model/Order.php:228`) projde přechody `OrderStatus` (stornovat jde rozpracovanou, potvrzenou a zaplacenou objednávku, odeslanou ne) a zaznamená `OrderCancelled`.
- **Zboží zpět na sklad:** `ReleaseReservationsHandler` v Inventory na `OrderCancelled` uvolní rezervace objednávky. Zásoba `onHand` se při zaplacení ani odeslání nesnižuje, drží se jen rezervace, takže jejich uvolnění znamená, že zboží je zase volné. Událost se pošle na `event.bus` po `save()` v `DoctrineOrderRepository`.
- **Co chybí:** command `CancelOrder` s handlerem a akce s tlačítkem ve staré administraci.

## Kde je problém: vrácení zaplacené částky
V `src/` není nic, co by peníze vracelo. Chybí platební brána, klient platebního API, událost i stav pro refundaci. Jediná stopa je secret `PAYMENT_API_KEY`, který žádný kód nepoužívá. Udělat to by znamenalo:
- napsat integraci s platební bránou, možná i s novým balíčkem (ten bez zeptání přidat nesmím),
- založit pro platby nový kontext nebo modul, který bude reagovat na `OrderCancelled`,
- možná do `OrderCancelled` doplnit `paidAmount` a to, jestli objednávka byla zaplacená. Bez toho handler nepozná, kolik a jestli vůbec vracet. Je to změna veřejné doménové události.

Kdybych přidal jen tlačítko „Stornovat“ a o peníze se nepostaral, obsluha by mohla věřit, že zákazník peníze dostal, a on by je nedostal.

## Co navrhuji
1. **Teď:** command `CancelOrder` s handlerem a testy, akce `order_cancel` ve staré administraci (POST, `csrf_field()`, `auth_require('obchod')`, `csrf_check()`, volání přes `legacy_command()`) a akceptační test. Zboží se vrátí na sklad přes existující událost.
2. **Vrácení peněz:** u zaplacené objednávky by detail zatím jasně říkal, že peníze je třeba vrátit ručně, a uvedl by částku. Automatické vrácení by přišlo jako samostatný úkol, až budu vědět, přes jakou bránu a jaké API.

Potřebuji od vás tři odpovědi:
- Stačí bod 1 s ručním vrácením peněz, nebo má vrácení být automatické? Pokud automatické, přes jakou platební bránu?
- Smí stornovat role `obchod`, nebo jen `admin`?
- Má být důvod storna (`cancellationNote`) povinné pole formuláře?