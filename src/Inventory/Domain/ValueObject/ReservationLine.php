<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Ordering\Domain\ValueObject\ProductId;

/**
 * Požadavek na rezervaci: kolik kusů kterého zboží.
 */
final readonly class ReservationLine
{
    public function __construct(
        public ProductId $productId,
        public int $quantity,
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Reserved quantity must be positive');
        }
    }
}
