<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Inventory\Domain\Exception\InsufficientStockException;
use App\Inventory\Domain\Exception\NothingReservedException;
use App\Inventory\Domain\Model\StockItem;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StockItem::class)]
final class StockItemTest extends TestCase
{
    #[Test]
    public function reservationLowersAvailableStock(): void
    {
        $stockItem = new StockItem(ProductId::generate(), 10);

        $stockItem->reserve(OrderId::generate(), 3);

        self::assertSame(10, $stockItem->onHand);
        self::assertSame(7, $stockItem->available());
    }

    #[Test]
    public function reservationCannotExceedStock(): void
    {
        $stockItem = new StockItem(ProductId::generate(), 2);

        $this->expectException(InsufficientStockException::class);
        $stockItem->reserve(OrderId::generate(), 3);
    }

    #[Test]
    public function releaseReturnsReservedPieces(): void
    {
        $stockItem = new StockItem(ProductId::generate(), 10);
        $orderId = OrderId::generate();
        $stockItem->reserve($orderId, 3);
        $stockItem->reserve($orderId, 1);

        $stockItem->release($orderId);

        self::assertSame(10, $stockItem->available());
        self::assertFalse($stockItem->hasReservationFor($orderId));
    }

    #[Test]
    public function onlyReservedPiecesCanBeReleased(): void
    {
        $stockItem = new StockItem(ProductId::generate(), 10);

        $this->expectException(NothingReservedException::class);
        $stockItem->release(OrderId::generate());
    }
}
