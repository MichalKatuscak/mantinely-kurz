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
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Akceptační kritérium 1 z docs/plany/zmena-mnozstvi.md: množství jde změnit
 * jen u rozpracované objednávky, i ze staré administrace.
 */
final class ChangeItemQuantityTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        TestDatabase::reset();
        // stará administrace drží spojení v globální proměnné
        $GLOBALS['db'] = null;
        $this->client = self::createClient();
        $this->client->disableReboot();
        Catalog::seed(self::getContainer()->get(Connection::class));
        $this->client->loginUser(
            self::getContainer()->get(DemoCustomerProvider::class)->loadUserByIdentifier('sprava@example.com'),
        );
    }

    #[Test]
    public function quantityInConfirmedOrderStaysUnchanged(): void
    {
        // příprava: potvrzená objednávka, klávesnice 2 × 300 Kč, sleva 100 Kč
        $orders = self::getContainer()->get(OrderRepository::class);
        $order = Order::place(OrderId::generate(), CustomerId::generate());
        $order->addItem(ProductId::fromString(Catalog::KEYBOARD), 2, new Money(300_00, Currency::CZK));
        $order->applyDiscount(new Money(100_00, Currency::CZK));
        $order->confirm();
        $orders->save($order);

        // akce: obsluha ve staré administraci změní množství na 3 kusy
        $this->client->request(
            'POST',
            '/admin/legacy/order_item_quantity?id='.$order->id->value,
            ['product' => Catalog::KEYBOARD, 'quantity' => '3'],
        );

        // ověření: chyba, množství i částky beze změny
        self::assertSelectorTextContains('.flash.error', 'rozpracované objednávky');
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $unchanged = $orders->get($order->id);
        self::assertSame(2, $unchanged->items[0]->quantity);
        self::assertSame(600_00, $unchanged->totalAmount()->amountInCents);
        self::assertSame(500_00, $unchanged->paidAmount()->amountInCents);
    }

    #[Test]
    public function quantityInDraftOrderChangesFromOrderDetail(): void
    {
        $order = Order::place(OrderId::generate(), CustomerId::generate());
        $order->addItem(ProductId::fromString(Catalog::KEYBOARD), 2, new Money(300_00, Currency::CZK));
        self::getContainer()->get(OrderRepository::class)->save($order);

        $this->client->request('GET', '/admin/legacy/order?id='.$order->id->value);
        $this->client->submitForm('Změnit', ['quantity' => '3']);

        self::assertSelectorTextContains('.flash', 'Množství položky změněno');
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(3, self::getContainer()->get(OrderRepository::class)->get($order->id)->items[0]->quantity);
    }
}
