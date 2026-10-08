<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Export;

use App\Ordering\Domain\Model\OrderItem;
use App\SharedKernel\Domain\Money;

/**
 * Řádek exportu objednávek pro účetní.
 */
final readonly class InvoiceLine
{
    public function __construct(
        public string $productId,
        public int $quantity,
        public Money $price,
    ) {}

    public static function fromItem(OrderItem $item): self
    {
        return new self($item->productId->value, $item->quantity, $item->unitPrice);
    }

    /** @param array{productId: string, quantity: int, price: Money} $line */
    public static function fromArray(array $line): self
    {
        return new self($line['productId'], $line['quantity'], $line['price']);
    }

    public function priceInCents(): int
    {
        return $this->price->amountInCents;
    }

    public function subtotalInCents(): int
    {
        return $this->price->multiply($this->quantity)->amountInCents;
    }
}
