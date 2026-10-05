<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Ordering\Domain\ValueObject\ProductId;

final class InsufficientStockException extends \DomainException
{
    public static function forProduct(ProductId $productId, int $requested, int $available): self
    {
        return new self(sprintf(
            'Cannot reserve %d pieces of product "%s", only %d available.',
            $requested,
            $productId->value,
            $available,
        ));
    }
}
