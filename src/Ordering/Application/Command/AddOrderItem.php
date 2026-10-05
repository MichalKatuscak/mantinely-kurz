<?php

declare(strict_types=1);

namespace App\Ordering\Application\Command;

use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\Money;

final readonly class AddOrderItem
{
    public function __construct(
        public OrderId $orderId,
        public ProductId $productId,
        public int $quantity,
        public Money $unitPrice,
    ) {}
}
