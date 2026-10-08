<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Exception;

final class InvalidQuantityException extends \DomainException
{
    public static function mustBePositive(int $quantity): self
    {
        return new self(sprintf('Quantity must be positive, %d given.', $quantity));
    }
}
