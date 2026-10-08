<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Exception\StockItemNotFoundException;
use App\Inventory\Domain\Model\StockItem;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;

interface StockItemRepository
{
    /** @throws StockItemNotFoundException */
    public function get(ProductId $productId): StockItem;

    /**
     * Skladové položky, na kterých objednávka drží rezervaci.
     *
     * @return list<StockItem>
     */
    public function reservedFor(OrderId $orderId): array;

    public function save(StockItem $stockItem): void;
}
