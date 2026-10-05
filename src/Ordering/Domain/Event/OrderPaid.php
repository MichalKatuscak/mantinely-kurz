<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\OrderId;

final readonly class OrderPaid
{
    public function __construct(
        public OrderId $orderId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
