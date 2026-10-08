<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Inventory\Application\EventHandler\ReserveStockHandler;
use App\Inventory\Domain\Repository\StockItemRepository;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ReserveStockHandler::class)]
final class ReserveStockTest extends KernelTestCase
{
    protected function setUp(): void
    {
        TestDatabase::reset();
        self::bootKernel();
        Catalog::seed(self::getContainer()->get(Connection::class));
    }

    #[Test]
    public function addedItemReservesStockForTheOrder(): void
    {
        $keyboard = ProductId::fromString(Catalog::KEYBOARD);
        $order = Order::place(OrderId::generate(), CustomerId::generate());
        $order->addItem($keyboard, 3, new Money(500_00, Currency::CZK));
        $order->addItem($keyboard, 1, new Money(500_00, Currency::CZK));

        self::getContainer()->get(OrderRepository::class)->save($order);

        $stockItem = self::getContainer()->get(StockItemRepository::class)->get($keyboard);
        self::assertSame(6, $stockItem->available());
        self::assertCount(1, $stockItem->reservations());
        self::assertSame(4, $stockItem->reservations()[0]->quantity);
        self::assertTrue($stockItem->reservations()[0]->orderId->equals($order->id));
    }
}
