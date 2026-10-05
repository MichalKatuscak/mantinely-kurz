<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Inventory\Application\EventHandler\ReleaseReservationsHandler;
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

#[CoversClass(ReleaseReservationsHandler::class)]
final class ReleaseReservationsTest extends KernelTestCase
{
    protected function setUp(): void
    {
        TestDatabase::reset();
        self::bootKernel();
        Catalog::seed(self::getContainer()->get(Connection::class));
    }

    #[Test]
    public function cancelledOrderReleasesItsReservations(): void
    {
        $orders = self::getContainer()->get(OrderRepository::class);
        $stockItems = self::getContainer()->get(StockItemRepository::class);
        $keyboard = ProductId::fromString(Catalog::KEYBOARD);

        $order = Order::place(OrderId::generate(), CustomerId::generate());
        $order->addItem($keyboard, 3, new Money(500_00, Currency::CZK));
        $orders->save($order);
        self::assertSame(7, $stockItems->get($keyboard)->available());

        $order->cancel('customer request', new \DateTimeImmutable());
        $orders->save($order);

        self::assertSame(10, $stockItems->get($keyboard)->available());
    }
}
