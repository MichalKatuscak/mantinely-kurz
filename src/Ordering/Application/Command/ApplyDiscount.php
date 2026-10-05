<?php

declare(strict_types=1);

namespace App\Ordering\Application\Command;

use App\Ordering\Domain\ValueObject\OrderId;
use App\SharedKernel\Domain\Money;

final readonly class ApplyDiscount
{
    public function __construct(
        public OrderId $orderId,
        public Money $discount,
    ) {}
}
