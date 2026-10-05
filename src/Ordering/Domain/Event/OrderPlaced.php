<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;

final readonly class OrderPlaced
{
    public function __construct(
        public OrderId $orderId,
        public CustomerId $customerId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
