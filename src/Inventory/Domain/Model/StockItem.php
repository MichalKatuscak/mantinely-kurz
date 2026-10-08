<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Model;

use App\Inventory\Domain\Exception\InsufficientStockException;
use App\Inventory\Domain\Exception\NothingReservedException;
use App\Inventory\Domain\ValueObject\Reservation;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\AggregateRoot;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'stock_items')]
final class StockItem extends AggregateRoot
{
    /**
     * Rezervace podle objednávky: ID objednávky => počet kusů.
     *
     * @var array<string, int>
     */
    #[ORM\Column(type: 'json')]
    private array $reservations = [];

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'product_id')]
        public readonly ProductId $productId,
        #[ORM\Column]
        public private(set) int $onHand,
    ) {
        if ($onHand < 0) {
            throw new \InvalidArgumentException('Stock on hand cannot be negative');
        }
    }

    /** Kusy, které jde ještě rezervovat. */
    public function available(): int
    {
        return $this->onHand - $this->reserved();
    }

    public function reserved(): int
    {
        return array_sum($this->reservations);
    }

    public function reserve(OrderId $orderId, int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Reserved quantity must be positive');
        }

        // INVARIANT: rezervace nepřesáhne zásobu.
        if ($quantity > $this->available()) {
            throw InsufficientStockException::forProduct($this->productId, $quantity, $this->available());
        }

        $this->reservations[$orderId->value] = ($this->reservations[$orderId->value] ?? 0) + $quantity;
    }

    public function release(OrderId $orderId): void
    {
        // INVARIANT: uvolnit jde jen to, co je rezervované.
        if (!$this->hasReservationFor($orderId)) {
            throw NothingReservedException::forOrder($this->productId, $orderId);
        }

        unset($this->reservations[$orderId->value]);
    }

    public function hasReservationFor(OrderId $orderId): bool
    {
        return isset($this->reservations[$orderId->value]);
    }

    /** @return list<Reservation> */
    public function reservations(): array
    {
        $reservations = [];
        foreach ($this->reservations as $orderId => $quantity) {
            $reservations[] = new Reservation(OrderId::fromString($orderId), $quantity);
        }

        return $reservations;
    }

    /** Naskladnění od dodavatele. */
    public function receive(int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Received quantity must be positive');
        }

        $this->onHand += $quantity;
    }
}
