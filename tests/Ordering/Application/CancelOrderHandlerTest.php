<?php

declare(strict_types=1);

namespace App\Tests\Ordering\Application;

use App\Inventory\Domain\Repository\StockItemRepository;
use App\Ordering\Application\Command\CancelOrder;
use App\Ordering\Application\Handler\CancelOrderHandler;
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
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(CancelOrderHandler::class)]
final class CancelOrderHandlerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        TestDatabase::reset();
        self::bootKernel();
        Catalog::seed(self::getContainer()->get(Connection::class));
    }

    #[Test]
    public function paidOrderIsCancelledRefundedAndStockReturned(): void
    {
        $orders = self::getContainer()->get(OrderRepository::class);
        $stockItems = self::getContainer()->get(StockItemRepository::class);
        $keyboard = ProductId::fromString(Catalog::KEYBOARD);

        $order = Order::place(OrderId::generate(), CustomerId::generate());
        $order->addItem($keyboard, 2, new Money(300_00, Currency::CZK));
        $order->applyDiscount(new Money(100_00, Currency::CZK));
        $order->confirm();
        $order->markPaid();
        $orders->save($order);
        self::assertSame(8, $stockItems->get($keyboard)->available());

        self::getContainer()->get(MessageBusInterface::class)
            ->dispatch(new CancelOrder($order->id, 'Zákazník si to rozmyslel'));

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $cancelled = $orders->get($order->id);
        self::assertSame(OrderStatus::Cancelled, $cancelled->status);
        self::assertSame('Zákazník si to rozmyslel', $cancelled->cancellationNote);
        self::assertTrue($cancelled->refund->equals(new Money(500_00, Currency::CZK)));
        self::assertSame(10, $stockItems->get($keyboard)->available());
    }
}
