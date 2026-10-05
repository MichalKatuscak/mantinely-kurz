<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Exception;

use App\SharedKernel\Domain\Currency;

final class CurrencyMismatchException extends \DomainException
{
    public static function forOrder(Currency $orderCurrency, Currency $given): self
    {
        return new self(sprintf(
            'Order is in %s, amount in %s cannot be used.',
            $orderCurrency->value,
            $given->value,
        ));
    }
}
