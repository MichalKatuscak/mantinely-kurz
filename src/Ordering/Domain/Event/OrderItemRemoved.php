<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;

final readonly class OrderItemRemoved
{
    public function __construct(
        public OrderId $orderId,
        public ProductId $productId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
