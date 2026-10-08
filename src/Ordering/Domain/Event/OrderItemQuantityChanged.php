<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;

final readonly class OrderItemQuantityChanged
{
    public function __construct(
        public OrderId $orderId,
        public ProductId $productId,
        public int $quantity,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
