<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\SharedKernel\Domain\Money;

final readonly class OrderCancelled
{
    public function __construct(
        public OrderId $orderId,
        public CustomerId $customerId,
        public string $reason,
        public \DateTimeImmutable $occurredAt,
        // Kolik zákazník dostane zpět; nula, když ještě nezaplatil.
        public Money $refund,
    ) {}
}
