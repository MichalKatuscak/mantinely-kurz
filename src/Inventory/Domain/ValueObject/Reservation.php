<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Ordering\Domain\ValueObject\OrderId;

/**
 * Kusy zboží, které drží jedna objednávka.
 */
final readonly class Reservation
{
    public function __construct(
        public OrderId $orderId,
        public int $quantity,
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Reserved quantity must be positive');
        }
    }
}
