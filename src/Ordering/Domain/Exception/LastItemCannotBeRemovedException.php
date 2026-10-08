<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Exception;

use App\Ordering\Domain\ValueObject\ProductId;

final class LastItemCannotBeRemovedException extends \DomainException
{
    public static function forProduct(ProductId $productId): self
    {
        return new self(sprintf('Product "%s" is the last item of the order and cannot be removed.', $productId->value));
    }
}
