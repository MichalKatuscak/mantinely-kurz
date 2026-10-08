<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Exception;

use App\SharedKernel\Domain\Money;

final class DiscountExceedsItemsTotalException extends \DomainException
{
    public static function forItemsTotal(Money $discount, Money $itemsTotal): self
    {
        return new self(sprintf(
            'Discount %d exceeds items total %d %s.',
            $discount->amountInCents,
            $itemsTotal->amountInCents,
            $itemsTotal->currency->value,
        ));
    }
}
