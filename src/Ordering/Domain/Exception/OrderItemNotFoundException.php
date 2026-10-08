<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Exception;

use App\Ordering\Domain\ValueObject\ProductId;

final class OrderItemNotFoundException extends \DomainException
{
    public static function forProduct(ProductId $productId): self
    {
        return new self(sprintf('Order has no item for product "%s".', $productId->value));
    }
}
