<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Exception;

final class EmptyOrderException extends \DomainException
{
    public static function cannotConfirm(): self
    {
        return new self('Cannot confirm an order without items.');
    }
}
