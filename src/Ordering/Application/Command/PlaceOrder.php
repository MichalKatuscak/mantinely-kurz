<?php

declare(strict_types=1);

namespace App\Ordering\Application\Command;

use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;

final readonly class PlaceOrder
{
    public function __construct(
        public OrderId $orderId,
        public CustomerId $customerId,
    ) {}
}
