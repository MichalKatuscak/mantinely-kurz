<?php

declare(strict_types=1);

namespace App\Ordering\Application\Command;

use App\Ordering\Domain\ValueObject\OrderId;

final readonly class CancelOrder
{
    public function __construct(
        public OrderId $orderId,
        public string $reason,
    ) {}
}
