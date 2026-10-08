<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Legacy;

use App\Identity\Infrastructure\Security\DemoCustomerProvider;
use App\Ordering\Domain\Model\Order;
use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use App\Tests\Support\Catalog;
use App\Tests\Support\TestDatabase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Akceptační kritérium storna: odeslanou objednávku stornovat nejde,
 * ani ze staré administrace.
 */
final class CancelShippedOrderTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        TestDatabase::reset();
        // stará administrace drží spojení v globální proměnné
        $GLOBALS['db'] = null;
        $this->client = self::createClient();
        Catalog::seed(self::getContainer()->get(Connection::class));
        $this->client->loginUser(
            self::getContainer()->get(DemoCustomerProvider::class)->loadUserByIdentifier('sprava@example.com'),
        );
    }

    #[Test]
    public function shippedOrderStaysShipped(): void
    {
        // příprava: odeslaná objednávka
        $order = Order::place(OrderId::generate(), CustomerId::generate());
        $order->addItem(ProductId::fromString(Catalog::KEYBOARD), 2, new Money(500_00, Currency::CZK));
        $order->confirm();
        $order->markPaid();
        $order->ship();
        self::getContainer()->get(OrderRepository::class)->save($order);
        $id = $order->id->value;

        // akce: stejný požadavek jako tlačítko storna, id v adrese i v těle formuláře
        $this->client->request('POST', '/admin/legacy/order_cancel?id='.$id, [
            'id' => $id,
            'reason' => 'Zákazník si to rozmyslel',
        ]);

        // ověření: objednávka zůstala odeslaná
        $status = self::getContainer()->get(Connection::class)
            ->fetchOne('SELECT status FROM orders WHERE id = ?', [$id]);
        self::assertSame('shipped', $status);
    }
}
