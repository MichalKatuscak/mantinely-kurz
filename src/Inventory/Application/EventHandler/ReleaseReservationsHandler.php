<?php

declare(strict_types=1);

namespace App\Inventory\Application\EventHandler;

use App\Inventory\Domain\Repository\StockItemRepository;
use App\Ordering\Domain\Event\OrderCancelled;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Stornovaná objednávka uvolní všechny své rezervace.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class ReleaseReservationsHandler
{
    public function __construct(
        private StockItemRepository $stockItems,
    ) {}

    public function __invoke(OrderCancelled $event): void
    {
        foreach ($this->stockItems->reservedFor($event->orderId) as $stockItem) {
            $stockItem->release($event->orderId);
            $this->stockItems->save($stockItem);
        }
    }
}
