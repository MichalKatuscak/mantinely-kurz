<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Ordering\Domain\ValueObject\ProductId;

final class StockItemNotFoundException extends \DomainException
{
    public static function forProduct(ProductId $productId): self
    {
        return new self(sprintf('No stock item for product "%s".', $productId->value));
    }
}
