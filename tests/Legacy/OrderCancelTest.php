<?php

declare(strict_types=1);

namespace App\Tests\Legacy;

use App\Identity\Infrastructure\Security\DemoCustomerProvider;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Repository\StockItemRepository;
use App\Legacy\Admin\OrderController;
use App\Ordering\Domain\Model\Order;
use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\OrderStatus;
use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use App\Tests\Support\Catalog;
use App\Tests\Support\TestDatabase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(OrderController::class)]
final class OrderCancelTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        TestDatabase::reset();
        // stara administrace si drzi spojeni v globalni promenne
        $GLOBALS['db'] = null;
        $this->client = self::createClient();
        $this->client->disableReboot();
        Catalog::seed(self::getContainer()->get(Connection::class));
        $this->client->loginUser(
            self::getContainer()->get(DemoCustomerProvider::class)->loadUserByIdentifier('sprava@example.com'),
        );
    }

    #[Test]
    public function cancelledPaidOrderRefundsPaidAmountAndReturnsGoodsToStock(): void
    {
        $order = $this->paidOrderWithDiscount();

        $this->client->request('GET', '/admin/legacy/order?id='.$order->id->value);
        $this->client->submitForm('Stornovat objednávku', ['reason' => 'zákazník si to rozmyslel']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.flash', 'Zákazníkovi se vrací 850,00 Kč');
        self::assertSame(OrderStatus::Cancelled, $this->reloaded($order)->status);
        self::assertSame(10, $this->stock()->available());

        $note = self::getContainer()->get(Connection::class)
            ->fetchOne('SELECT note FROM order_notes WHERE order_id = ?', [$order->id->value]);
        self::assertSame('Storno: vrátit zákazníkovi 850,00 Kč', $note);
    }

    #[Test]
    public function shippedOrderCannotBeCancelled(): void
    {
        $order = $this->paidOrderWithDiscount();
        $order->ship();
        self::getContainer()->get(OrderRepository::class)->save($order);

        $this->client->request('POST', '/admin/legacy/order_cancel?id='.$order->id->value);

        self::assertSelectorTextContains('.flash.error', 'nelze stornovat');
        self::assertSame(OrderStatus::Shipped, $this->reloaded($order)->status);
        self::assertSame(8, $this->stock()->available());
    }

    private function paidOrderWithDiscount(): Order
    {
        $order = Order::place(OrderId::generate(), CustomerId::generate());
        $order->addItem(ProductId::fromString(Catalog::KEYBOARD), 2, new Money(500_00, Currency::CZK));
        $order->applyDiscount(new Money(150_00, Currency::CZK));
        $order->confirm();
        $order->markPaid();
        self::getContainer()->get(OrderRepository::class)->save($order);

        return $order;
    }

    private function reloaded(Order $order): Order
    {
        self::getContainer()->get('doctrine.orm.entity_manager')->clear();

        return self::getContainer()->get(OrderRepository::class)->get($order->id);
    }

    private function stock(): StockItem
    {
        self::getContainer()->get('doctrine.orm.entity_manager')->clear();

        return self::getContainer()->get(StockItemRepository::class)->get(ProductId::fromString(Catalog::KEYBOARD));
    }
}
