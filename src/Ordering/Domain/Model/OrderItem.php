<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Model;

use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\Money;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'order_items')]
final class OrderItem
{
    // Náhradní identita. Položka nemá doménové ID, přistupuje se k ní přes kořen.
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'lines')]
        #[ORM\JoinColumn(nullable: false)]
        private Order $order,
        #[ORM\Column(type: 'product_id')]
        public readonly ProductId $productId,
        #[ORM\Column]
        public private(set) int $quantity,
        #[ORM\Embedded(class: Money::class)]
        public readonly Money $unitPrice,
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }
    }

    public function increaseQuantity(int $by): void
    {
        if ($by < 1) {
            throw new \InvalidArgumentException('Quantity increment must be positive');
        }

        $this->quantity += $by;
    }

    public function changeQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        $this->quantity = $quantity;
    }

    public function subtotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
