<?php

declare(strict_types=1);

namespace App\Inventory\Application\EventHandler;

use App\Inventory\Domain\Repository\StockItemRepository;
use App\Ordering\Domain\Event\OrderItemAdded;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;
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
        $this->reserveLines($event->orderId, [
            ['productId' => $event->productId->value, 'quantity' => $event->quantity],
        ]);
    }

    private function reserveLines(OrderId $orderId, array $lines): void
    {
        foreach ($lines as $line) {
            $stockItem = $this->stockItems->get(ProductId::fromString($line['productId']));
            $stockItem->reserve($orderId, $line['quantity']);
            $this->stockItems->save($stockItem);
        }
    }
}
