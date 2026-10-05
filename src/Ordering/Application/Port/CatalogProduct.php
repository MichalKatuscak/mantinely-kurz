<?php

declare(strict_types=1);

namespace App\Ordering\Application\Port;

use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\Money;

final readonly class CatalogProduct
{
    public function __construct(
        public ProductId $id,
        public string $name,
        public Money $price,
    ) {}
}
