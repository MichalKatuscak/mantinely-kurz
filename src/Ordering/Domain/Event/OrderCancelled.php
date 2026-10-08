<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;

final readonly class OrderCancelled
{
    public function __construct(
        public OrderId $orderId,
        public CustomerId $customerId,
        public string $reason,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
