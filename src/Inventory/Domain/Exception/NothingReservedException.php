<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;

final class NothingReservedException extends \DomainException
{
    public static function forOrder(ProductId $productId, OrderId $orderId): self
    {
        return new self(sprintf(
            'Product "%s" has no reservation for order "%s".',
            $productId->value,
            $orderId->value,
        ));
    }
}
