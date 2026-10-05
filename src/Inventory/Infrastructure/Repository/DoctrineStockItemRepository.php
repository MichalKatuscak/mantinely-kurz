<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Repository;

use App\Inventory\Domain\Exception\StockItemNotFoundException;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Repository\StockItemRepository;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineStockItemRepository implements StockItemRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function get(ProductId $productId): StockItem
    {
        return $this->entityManager->find(StockItem::class, $productId)
            ?? throw StockItemNotFoundException::forProduct($productId);
    }

    public function reservedFor(OrderId $orderId): array
    {
        $all = $this->entityManager->getRepository(StockItem::class)->findAll();

        return array_values(array_filter(
            $all,
            static fn ($stockItem) => $stockItem->hasReservationFor($orderId),
        ));
    }

    public function save(StockItem $stockItem): void
    {
        $this->entityManager->persist($stockItem);
        $this->entityManager->flush();
    }
}
