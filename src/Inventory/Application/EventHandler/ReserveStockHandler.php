<?php

declare(strict_types=1);

namespace App\Inventory\Application\EventHandler;

use App\Inventory\Domain\Repository\StockItemRepository;
use App\Inventory\Domain\ValueObject\ReservationLine;
use App\Ordering\Domain\Event\OrderItemAdded;
use App\Ordering\Domain\ValueObject\OrderId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Položka přidaná do objednávky si rezervuje zboží na skladě.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class ReserveStockHandler
{
    public function __construct(
        private StockItemRepository $stockItems,
    ) {}

    public function __invoke(OrderItemAdded $event): void
    {
        $this->reserveLines($event->orderId, new ReservationLine($event->productId, $event->quantity));
    }

    private function reserveLines(OrderId $orderId, ReservationLine ...$lines): void
    {
        foreach ($lines as $line) {
            $stockItem = $this->stockItems->get($line->productId);
            $stockItem->reserve($orderId, $line->quantity);
            $this->stockItems->save($stockItem);
        }
    }
}
