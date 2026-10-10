model: claude-opus-5-5  nástroj: Claude Code 2.1.291
[1] Bash: cat src/SharedKernel/Domain/Money.php migrations/Version20261005115235.php phpstan-legacy.neon phpunit.dist.xml src/Legacy/Admin/orders.php; grep -n "orders.php\|cron.php" -B2 -A4 phpstan-legacy-basel
   → <?php  declare(strict_types=1);  namespace App\SharedKernel\Domain;  use Doctrine\ORM\Mapping as ORM;  #[ORM\Embeddable] final readonly class Money {     public function __construct(         #[ORM\Column]         public 
[2] Bash: sed -n 1,60p tests/Ordering/Domain/OrderTest.php; grep -n "cancel" tests/Ordering/Domain/OrderTest.php; grep -n "function audit_log\|function ids_to_sql" -A12 src/Legacy/lib/*.php
   → <?php  declare(strict_types=1);  namespace App\Tests\Ordering\Domain;  use App\Ordering\Domain\Event\OrderCancelled; use App\Ordering\Domain\Event\OrderConfirmed; use App\Ordering\Domain\Event\OrderItemAdded; use App\Ord
TEXT: Nejdřív domácí test na záznam částky k vrácení (nový soubor, existující `OrderTest` neupravuji).
[3] Write: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/tests/Ordering/Domain/OrderRefundTest.php
   → File created successfully at: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/tests/Ordering/Domain/OrderRefundTest.php (file state is current in your context — no need to Read it back)
[4] Edit: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
TEXT: To hlásil hook ještě před úpravou `Order`. Dokončuji agregát:
[5] Edit: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[6] Edit: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
TEXT: Migrace pro nové sloupce (existující objednávky dostanou nulu v měně objednávky):
[7] Write: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/migrations/Version20261010120000.php
   → File created successfully at: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/migrations/Version20261010120000.php (file state is current in your context — no need to Read it back)
TEXT: Teď stará administrace: detail ukáže částku k vrácení, akce ji oznámí a zapíše do historie.
[8] Edit: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Legacy/Admin/OrderController.php
   → The file /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Legacy/Admin/OrderController.php has been updated successfully.
[9] Edit: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Legacy/Admin/OrderController.php
   → The file /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Legacy/Admin/OrderController.php has been updated successfully.
[10] Edit: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Legacy/templates/orders/detail.php
   → The file /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Legacy/templates/orders/detail.php has been updated successfully.
TEXT: Hromadné storno v `orders.php` převádím na stejný příkaz, jinak by u zaplacených objednávek nezaznamenalo vrácení peněz a nechalo by rezervace viset:
[11] Edit: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Legacy/Admin/orders.php
   → The file /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/src/Legacy/Admin/orders.php has been updated successfully.
TEXT: Doplňuji akceptační testy (můj nový soubor z minulého kroku): částka k vrácení a hromadné storno.
[12] Edit: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/tests/Acceptance/Legacy/CancelOrderTest.php
[13] Bash: vendor/bin/phpunit --no-progress 2>&1 | tail -30
   → The file /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/tests/Acceptance/Legacy/CancelOrderTest.php has been updated successfully. (file state is current in your context — no need to Read it back)
   → PHPUnit 13.4.1 by Sebastian Bergmann and contributors.  Runtime:       PHP 8.4.26 Configuration: /home/michal/kopie/tmp.VGWiXXUrsc/aplikace/phpunit.dist.xml  Time: 00:02.743, Memory: 147.00 MB  [30;42mOK (122 tests, 238
[14] Bash: make check 2>&1 | grep -vE "^\s*$" | tail -45
   → #StandWithUkraine Infection - PHP Mutation Testing Framework version 0.35.6 [notice] You are running Infection with PCOV enabled. [notice] Running Infection with 1 thread. Running initial tests with PHPUnit version 13.4.
TEXT: Ještě ověřím, že migrace odpovídá mapování (porovnání s testovací databází postavenou z migrací, nic se nezapisuje):
[15] Bash: bin/console doctrine:schema:update --dump-sql --env=test 2>&1 | tail -15
   → CREATE TEMPORARY TABLE __temp__orders AS SELECT status, placed_at, id, customer_id, currency, discount_amount_in_cents, discount_currency, cancellation_note, refund_amount_in_cents, refund_currency FROM orders; DROP TABL
TEXT: Hotovo. Když obchod stornuje zaplacenou objednávku, uloží se u ní částka, kterou má zákazníkovi vrátit. Zboží se vrátí na sklad. Všechny testy (122) a `make check` prošly, Infection zabil 4 ze 4 mutantů. Nic jsem necommitoval a migraci jsem na dev databázi nespustil, protože to podle pravidel repozitáře potřebuje tvoje schválení.  ## Co a jak jsem rozhodl - **Částku k vrácení počítá a ukládá objed

VÝSLEDEK (16 kroků, 123 s):
Hotovo. Když obchod stornuje zaplacenou objednávku, uloží se u ní částka, kterou má zákazníkovi vrátit. Zboží se vrátí na sklad. Všechny testy (122) a `make check` prošly, Infection zabil 4 ze 4 mutantů. Nic jsem necommitoval a migraci jsem na dev databázi nespustil, protože to podle pravidel repozitáře potřebuje tvoje schválení.

## Co a jak jsem rozhodl
- **Částku k vrácení počítá a ukládá objednávka sama.** `Order` má novou vlastnost `refundDue` (sloupce `refund_amount_in_cents` a `refund_currency`). Metoda `cancel()` do ní u zaplacené objednávky uloží `paidAmount()`, tedy částku po slevě. U rozpracované a potvrzené objednávky zákazník nic neplatil, takže zůstane nula. Když se objednávka stornuje podruhé, zaznamenaná částka se nepřepíše.
- **Stará administrace částku jen ukáže.** V detailu stornované objednávky je řádek „Vrátit zákazníkovi (ručně, po stornu)“. Hláška po stornu zní třeba „zákazníkovi vraťte 800 Kč“ a storno s částkou se zapíše do historie objednávky (`audit_log`).
- **Hromadné storno v `orders.php` teď jde přes stejný příkaz `CancelOrder`, ne přes SQL.** Jinak by u zaplacených objednávek částku k vrácení nezaznamenalo a rezervace by zůstaly viset (to byl starý FIXME). **Tady se mění chování:** odeslané objednávky už hromadně stornovat nejde. Přeskočí se a hláška řekne, kolik jich bylo. Odpovídá to pravidlu domény „odeslanou zásilku storno nevrátí“.
- **Migrace `Version20261010120000`** přidá oba sloupce. Dříve stornované objednávky dostanou nulu v měně objednávky. Sloupce mají v databázi výchozí hodnotu, kterou mapování nemá, protože SQLite jinak nepřidá sloupec `NOT NULL`. Jiný rozdíl mezi migrací a mapováním není.

Zaznamenává se jen částka k vrácení, ne to, že ji obchod skutečně vrátil. Jestli chceš mít u objednávky i značku „vráceno“, je to další krok.

## Testy
- `tests/Ordering/Domain/OrderRefundTest.php` (nový): částka k vrácení u zaplacené, nezaplacené i opakovaně stornované objednávky, včetně slevy a měny EUR.
- `tests/Acceptance/Legacy/CancelOrderTest.php`:
  - storno z detailu: vrací se 800 Kč a sklad se vrátí ze 7 na 10 ks
  - odeslanou objednávku stornovat nejde
  - hromadné storno: zaplacená se stornuje s částkou 600 Kč, odeslaná zůstane

## Co zůstává
Cron v `cron.php` dál stornuje nezaplacené objednávky přímým `UPDATE`. Vracet se tam nic nemusí, ale rezervace na skladě po něm zůstanou viset. Cron běží mimo `/admin/legacy`, takže nemá přístup k command busu a oprava by potřebovala vlastní napojení. Proto jsem ho nechal na samostatný úkol.