<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\SharedKernel\Domain\Money;

/**
 * Zákazník má dostat zpět zaplacenou částku. Peníze vrací obchod ručně.
 */
final readonly class RefundRequested
{
    public function __construct(
        public OrderId $orderId,
        public CustomerId $customerId,
        public Money $amount,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
