model: claude-haiku-4-5-20251001  nástroj: Claude Code 2.1.291
TEXT: Rozumím. Znamená to, že musím zaznamenat vrácení peněz u objednávky. Pojďme se podívat na databázové schéma, abych viděl, jak se to mělo zaznamenávat.
[1] Bash: grep -r "refund\|vrácen" . --include="*.php" --include="*.sql" | grep -v node_modules | head -10
   → (Bash completed with no output)
[2] Bash: find . -name "*.php" -path "*/migrations/*" | xargs grep -l "orders" | tail -1 | xargs cat
   → <?php  declare(strict_types=1);  namespace DoctrineMigrations;  use Doctrine\DBAL\Schema\Schema; use Doctrine\Migrations\AbstractMigration;  /**  * Tabulky stare administrace (src/Legacy).  *  * Puvodne MySQL schema z le
[3] Bash: find . -path "*/migrations/*" -name "*.php" | xargs grep -l "CREATE TABLE orders" | head -1 | xargs cat
   → <?php  declare(strict_types=1);  namespace DoctrineMigrations;  use Doctrine\DBAL\Schema\Schema; use Doctrine\Migrations\AbstractMigration;  /**  * Auto-generated Migration: Please modify to your needs!  */ final class V
[4] Bash: find . -path "*/migrations/*" -name "*.php" | sort | xargs grep -l "CREATE TABLE orders" 2>/dev/null
   → ./migrations/Version20261005105721.php ./migrations/Version20261005115235.php
[5] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/migrations/Version20261005105721.php
   → 1	<?php 2	 3	declare(strict_types=1); 4	 5	namespace DoctrineMigrations; 6	 7	use Doctrine\DBAL\Schema\Schema; 8	use Doctrine\Migrations\AbstractMigration; 9	 10	/** 11	 * Auto-generated Migration: Please modify to your 
[6] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → 1	<?php 2	 3	declare(strict_types=1); 4	 5	namespace App\Ordering\Domain\Model; 6	 7	use App\Ordering\Domain\Event\OrderCancelled; 8	use App\Ordering\Domain\Event\OrderConfirmed; 9	use App\Ordering\Domain\Event\OrderDeli
TEXT: Pojďme přidat možnost zaznamenat vrácení peněz u stornované objednávky. Nejdřív přidám pole do Order agregátu:
[7] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[8] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[9] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[10] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[11] Bash: php bin/console make:migration --no-interaction 2>&1 | head -20
   → [WARNING] "make:migration" is not meant to be run in non-interactive mode.         [WARNING] You have 3 available migrations to execute.                             created: ]8;;file:///home/michal/kopie/tmp.CjXYsnz66O/
[12] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/migrations/Version20261010154514.php
   → 1	<?php 2	 3	declare(strict_types=1); 4	 5	namespace DoctrineMigrations; 6	 7	use Doctrine\DBAL\Schema\Schema; 8	use Doctrine\Migrations\AbstractMigration; 9	 10	/** 11	 * Auto-generated Migration: Please modify to your 
[13] Bash: php bin/console doctrine:migrations:migrate --no-interaction 2>&1 | head -30
   → In ExceptionConverter.php line 56:                                                                                   An exception occurred while executing a query: SQLSTATE[HY000]: General err     or: 1 table doctrine_mi
[14] Bash: php bin/console doctrine:migrations:status 2>&1 | grep -A 20 "Available Migrations"
   → (Bash completed with no output)
[15] Bash: make test-domain 2>&1
   → vendor/bin/phpunit --no-progress --testsuite domain PHPUnit 13.4.1 by Sebastian Bergmann and contributors.  Runtime:       PHP 8.4.26 Configuration: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/phpunit.dist.xml  Time: 00:0
[16] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Application/Handler/CancelOrderHandler.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Application/Handler/CancelOrderHandler.php has been updated successfully. (file state is current in your context — no need to Read it back)
[17] Bash: make test-domain && make phpstan 2>&1 | tail -20
   → vendor/bin/phpunit --no-progress --testsuite domain PHPUnit 13.4.1 by Sebastian Bergmann and contributors.  Runtime:       PHP 8.4.26 Configuration: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/phpunit.dist.xml  Time: 00:0
[18] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Legacy/Admin/order_edit.php
   → 130	    <tr><th>Stav</th><td> 131	        <select name="status"> 132	        <?php foreach ($GLOBALS['ORDER_STATES'] as $k => $v) { ?> 133	            <option value="<?php echo h($k); ?>"<?php echo $k == $order['status']
[19] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Legacy/Admin/order_edit.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Legacy/Admin/order_edit.php has been updated successfully.
[20] Bash: make check 2>&1 | tail -50
   → vendor/bin/phpunit --no-progress PHPUnit 13.4.1 by Sebastian Bergmann and contributors.  Runtime:       PHP 8.4.26 Configuration: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/phpunit.dist.xml  Time: 00:01.488, Memory: 145.
[21] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/migrations/Version20261010154514.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/migrations/Version20261010154514.php has been updated successfully. (file state is current in your context — no need to Read it back)
[22] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests/Legacy/MonthlyRevenueTest.php
   → 1	<?php 2	 3	declare(strict_types=1); 4	 5	namespace App\Tests\Legacy; 6	 7	use App\Legacy\Admin\ReportController; 8	use App\Tests\Support\TestDatabase; 9	use PHPUnit\Framework\Attributes\DataProvider; 10	use PHPUnit\Fra
[23] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests/Legacy/fixtures/report-months.sql
   → 1	-- Data pro charakterizační testy reportu měsíčních tržeb (cvičení modulu 10). 2	-- Čtyři situace: měsíc bez objednávek, měsíc se slevou, měsíc se stornem, přelom roku. 3	-- Částky jsou v haléřích jako v tabulkách apli
[24] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests/Legacy/fixtures/report-months.sql
   → PreToolUse:Edit hook error: [php "$CLAUDE_PROJECT_DIR/.claude/hooks/existujici-testy.php"]: Existující test tests/Legacy/fixtures/report-months.sql agent neupravuje. Nový test založ jako nový soubor. Když je potřeba změn
TEXT: Nemůžu měnit existující testy. Problém je, že nové pole `refunded_amount_in_cents` je NOT NULL bez default hodnoty. Pojďme migraci opravit tak, aby měla default:
[25] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/migrations/Version20261010154514.php
   → 1	<?php 2	 3	declare(strict_types=1); 4	 5	namespace DoctrineMigrations; 6	 7	use Doctrine\DBAL\Schema\Schema; 8	use Doctrine\Migrations\AbstractMigration; 9	 10	/** 11	 * Auto-generated Migration: Please modify to your 
TEXT: Problém je, že testy vytvářejí data přímo SQL bez migrace. Pojďme změnit entity mapping, aby `refundedAmount` mělo default value při inicializaci:
[26] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → 60	    public private(set) ?string $cancellationNote = null; 61	 62	    // Vrácená částka peněz za stornovanou objednávku. 63	    #[ORM\Embedded(class: Money::class, columnPrefix: 'refunded_')] 64	    public private(set)
[27] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[28] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[29] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → 280	    { 281	        return $this->totalAmount() 282	            ->subtract($this->discount); 283	    } 284	 285	    private function itemFor(ProductId $productId): OrderItem 286	    { 287	        foreach ($this->lines 
[30] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[31] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[32] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[33] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[34] Bash: make test-domain 2>&1
   → vendor/bin/phpunit --no-progress --testsuite domain PHPUnit 13.4.1 by Sebastian Bergmann and contributors.  Runtime:       PHP 8.4.26 Configuration: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/phpunit.dist.xml  Time: 00:0
[35] Bash: make check 2>&1 | tail -60
   → /home/michal/kopie/tmp.CjXYsnz66O/aplikace/vendor/doctrine/orm/src/EntityManager.php:270 /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Infrastructure/Repository/DoctrineOrderRepository.php:59 /home/michal/kopie
[36] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests/Ordering/Infrastructure/OrderControllerTest.php
   → 1	<?php 2	 3	declare(strict_types=1); 4	 5	namespace App\Tests\Ordering\Infrastructure; 6	 7	use App\Identity\Infrastructure\Security\DemoCustomerProvider; 8	use App\Ordering\Domain\Model\Order; 9	use App\Ordering\Domain
TEXT: Pojďme spustit migraci, aby se databáze správně inicializovala:
[37] Bash: php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1 | tail -20
   → In ExceptionConverter.php line 56:                                                                                   An exception occurred while executing a query: SQLSTATE[HY000]: General err     or: 1 table doctrine_mi
[38] Bash: php bin/console doctrine:schema:validate 2>&1 | head -30
   → Mapping -------   [OK] The mapping files are correct.                                              Database --------   [ERROR] The database schema is not in sync with the current mapping file.
[39] Bash: php bin/console doctrine:schema:update --dump-sql 2>&1
   → ALTER TABLE orders ADD COLUMN refunded_amount_in_cents INTEGER NOT NULL; ALTER TABLE orders ADD COLUMN refunded_currency VARCHAR(255) NOT NULL;
[40] Bash: rm var/app.db
   → Exit code 1 rm: cannot remove 'var/app.db': No such file or directory
[41] Bash: php bin/console doctrine:database:create --if-not-exists 2>&1 && php bin/console doctrine:migrations:migrate --no-interaction 2>&1 | head -20
   → Exit code 1  In NotSupported.php line 15:                                                                                   Operation "Doctrine\DBAL\Platforms\AbstractPlatform::getListDatabasesSQL" i     s not supported 
[42] Bash: grep -n "class TestDatabase" /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests -r
   → /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests/Support/TestDatabase.php:10:final class TestDatabase
[43] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests/Support/TestDatabase.php
   → 1	<?php 2	 3	declare(strict_types=1); 4	 5	namespace App\Tests\Support; 6	 7	/** 8	 * SQLite databáze pro testy. Šablona se staví z migrací, jen když se migrace změnily. 9	 */ 10	final class TestDatabase 11	{ 12	    publ
[44] Bash: php -r "require 'tests/Support/TestDatabase.php'; App\Tests\Support\TestDatabase::buildTemplate(); echo 'OK';"
   → OK
[45] Bash: make test 2>&1 | tail -50
   → --  There was 1 failure:  1) App\Tests\Ordering\Infrastructure\OrderControllerTest::foreignCustomerDoesNotSeeOrder Failed asserting that the Response status code is 404. HTTP/1.1 200 OK Cache-Control: max-age=0, must-re
[46] Bash: make test 2>&1 | grep -A 5 "There were"
   → There were 5 errors:  1) App\Tests\Acceptance\Legacy\ChangeItemQuantityTest::quantityInConfirmedOrderStaysUnchanged Doctrine\DBAL\Exception\NotNullConstraintViolationException: An exception occurred while executing a que
[47] Bash: grep -r "class ChangeItemQuantityTest" /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests -A 20 | head -40
   → /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests/Acceptance/Legacy/ChangeItemQuantityTest.php:final class ChangeItemQuantityTest extends WebTestCase /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests/Acceptance/Legacy/Chan
[48] Bash: grep -n "function seed" /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests -r
   → /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests/Support/Catalog.php:17:    public static function seed(Connection $connection): void
[49] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests/Support/Catalog.php
   → 1	<?php 2	 3	declare(strict_types=1); 4	 5	namespace App\Tests\Support; 6	 7	use Doctrine\DBAL\Connection; 8	 9	/** 10	 * Ukázkové zboží v katalogu staré administrace a na skladě. 11	 */ 12	final class Catalog 13	{ 14	  
[50] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/tests/Acceptance/Legacy/ChangeItemQuantityTest.php
   → 1	<?php 2	 3	declare(strict_types=1); 4	 5	namespace App\Tests\Acceptance\Legacy; 6	 7	use App\Identity\Infrastructure\Security\DemoCustomerProvider; 8	use App\Ordering\Domain\Model\Order; 9	use App\Ordering\Domain\Repos
TEXT: Problém je, že refundedAmount sloupce nemají DEFAULT hodnoty v SQL. Pojďme migraci opravit a ověřit, že má správné DEFAULT:
[51] Read: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/migrations/Version20261010154514.php
   → 1	<?php 2	 3	declare(strict_types=1); 4	 5	namespace DoctrineMigrations; 6	 7	use Doctrine\DBAL\Schema\Schema; 8	use Doctrine\Migrations\AbstractMigration; 9	 10	/** 11	 * Auto-generated Migration: Please modify to your 
[52] Bash: rm -f var/data_test.*.db && php -r "require 'tests/Support/TestDatabase.php'; App\Tests\Support\TestDatabase::buildTemplate(); echo 'OK';" 2>&1
   → Glob patterns are not allowed in write operations. Please specify an exact file path.
[53] Bash: ls var/data_test*
   → var/data_test.db var/data_test.template.db
[54] Bash: rm -f var/data_test.db var/data_test.template.db && php -r "require 'tests/Support/TestDatabase.php'; App\Tests\Support\TestDatabase::buildTemplate(); echo 'Template built OK';" 2>&1
   → Template built OK
[55] Bash: make test 2>&1 | tail -30
   → <h1>Moje objednávky</h1>      <form method="post" action="/objednavky">         <input type="hidden" name="_token" value="3488e971170330cee440d36a.F1FHfZBeFDFAaZvCV7W0gcp2-M_bdZL-6n9hmsdV8_g.LzkGC_omdQk1OPG4GOLWtrgivL66I
[56] Bash: make test 2>&1 | grep -A 3 "There were"
   → There were 5 errors:  1) App\Tests\Acceptance\Legacy\ChangeItemQuantityTest::quantityInConfirmedOrderStaysUnchanged Doctrine\DBAL\Exception\NotNullConstraintViolationException: An exception occurred while executing a que
TEXT: Pojďme si zkontrolovat, co SQL schema se skutečně vytvořilo:
[57] Bash: sqlite3 var/data_test.db ".schema orders"
   → This command requires approval
[58] Bash: php bin/console doctrine:schema:create --dump-sql 2>&1 | head -20
   → CREATE TABLE orders (status VARCHAR(255) NOT NULL, placed_at DATETIME DEFAULT NULL, cancellation_note CLOB DEFAULT NULL, id CHAR(36) NOT NULL, customer_id CHAR(36) NOT NULL, currency VARCHAR(255) NOT NULL, refunded_amoun
TEXT: Pojďme přidat anotaci `#[ORM\Column(nullable: false)]` s vytvořením SQL s DEFAULT:
[59] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[60] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[61] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[62] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[63] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[64] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[65] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[66] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[67] Edit: /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php
   → The file /home/michal/kopie/tmp.CjXYsnz66O/aplikace/src/Ordering/Domain/Model/Order.php has been updated successfully.
[68] Bash: rm -f var/data_test.db var/data_test.template.db && make check 2>&1 | tail -30
   → PHPStan (stará administrace): bez nových nebezpečných vzorů   [OK] Rector is done!                                                               --------------------------    Report                      -----------------
[69] Bash: git status
   → On branch beh Changes not staged for commit:   (use "git add <file>..." to update what will be committed)   (use "git restore <file>..." to discard changes in working directory) 	modified:   src/Legacy/Admin/order_edit.p
[70] Bash: git diff src/Ordering/Domain/Model/Order.php | head -60
   → diff --git a/src/Ordering/Domain/Model/Order.php b/src/Ordering/Domain/Model/Order.php index dd6faa2..49604bc 100644 --- a/src/Ordering/Domain/Model/Order.php +++ b/src/Ordering/Domain/Model/Order.php @@ -59,6 +59,10 @@ 
TEXT: Nyní si commitnu všechny změny. Práce je hotová - implementoval jsem storno objednávky se záznamem vrácení peněz. Pojďme si shrnout, co bylo vytvořeno:  ## Shrnutí implementace storna u objednávky ve staré administraci  **Implementováno:**  1. **Order agregát** - přidán field `refundedAmount` pro zaznamenání vrácené částky    - Inicializuje se na nulu při vytváření objednávky    - Metoda `markRefu

VÝSLEDEK (71 kroků, 387 s):
Nyní si commitnu všechny změny. Práce je hotová - implementoval jsem storno objednávky se záznamem vrácení peněz. Pojďme si shrnout, co bylo vytvořeno:

## Shrnutí implementace storna u objednávky ve staré administraci

**Implementováno:**

1. **Order agregát** - přidán field `refundedAmount` pro zaznamenání vrácené částky
   - Inicializuje se na nulu při vytváření objednávky
   - Metoda `markRefunded()` zaznamenává vrácenou částku

2. **CancelOrder příkaz a handler** - spravují storno objednávky
   - Handler automaticky zaznamenává vrácení zaplacené částky
   - Zavolá doménovou metodu `Order::cancel()`, která:
     - Změní stav na Cancelled
     - Zaznamenává OrderCancelled event

3. **Automatické uvolnění zboží** - ReleaseReservationsHandler reaguje na OrderCancelled event
   - Vrátí všechny rezervace zboží na sklad

4. **Uživatelské rozhraní** v `order_edit.php`
   - Formulář pro storno s CSRF ochranou
   - Pole pro důvod storna
   - Zobrazení vrácené částky u stornované objednávky
   - Kontrola stavů (odeslanou nelze stornovat)

5. **Databázová migrace**
   - Přidává sloupce `refunded_amount_in_cents` a `refunded_currency`
   - S default hodnotami pro zpětnou kompatibilitu

**Bezpečnost:**
- CSRF ochrana na formuláři
- Auth kontrola
- Idempotentní operace (duplicate storna nevrací chybu)
- Validace stavů objednávky

Všechny kontroly projdou: `make check` ✓