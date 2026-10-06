<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Model;

use App\Ordering\Domain\Event\OrderCancelled;
use App\Ordering\Domain\Event\OrderConfirmed;
use App\Ordering\Domain\Event\OrderDelivered;
use App\Ordering\Domain\Event\OrderItemAdded;
use App\Ordering\Domain\Event\OrderItemQuantityChanged;
use App\Ordering\Domain\Event\OrderItemRemoved;
use App\Ordering\Domain\Event\OrderPaid;
use App\Ordering\Domain\Event\OrderPlaced;
use App\Ordering\Domain\Event\OrderShipped;
use App\Ordering\Domain\Exception\CurrencyMismatchException;
use App\Ordering\Domain\Exception\DiscountExceedsItemsTotalException;
use App\Ordering\Domain\Exception\EmptyOrderException;
use App\Ordering\Domain\Exception\InvalidOrderStateTransitionException;
use App\Ordering\Domain\Exception\InvalidQuantityException;
use App\Ordering\Domain\Exception\LastItemCannotBeRemovedException;
use App\Ordering\Domain\Exception\OrderItemNotFoundException;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\OrderStatus;
use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\AggregateRoot;
use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
final class Order extends AggregateRoot
{
    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    private Collection $lines;

    /**
     * Položky jen ke čtení: vrací se kopie, přidává se přes addItem().
     *
     * @var list<OrderItem>
     */
    public array $items {
        get => array_values($this->lines->toArray());
    }

    // Asymetrická viditelnost: přečte kdokoli, zapíše jen kód uvnitř třídy.
    #[ORM\Column(enumType: OrderStatus::class)]
    public private(set) OrderStatus $status;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $placedAt = null;

    // Sleva na celou objednávku. Kniha ji nemá, kurz ano (viz README).
    #[ORM\Embedded(class: Money::class, columnPrefix: 'discount_')]
    public private(set) Money $discount;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'order_id')]
        public readonly OrderId $id,
        #[ORM\Column(type: 'customer_id')]
        public readonly CustomerId $customerId,
        #[ORM\Column(enumType: Currency::class)]
        public readonly Currency $currency,
    ) {
        $this->status = OrderStatus::Draft;
        $this->lines = new ArrayCollection();
        $this->discount = Money::zero($currency);
    }

    public static function place(OrderId $id, CustomerId $customerId, Currency $currency = Currency::CZK): self
    {
        $order = new self($id, $customerId, $currency);
        $order->record(new OrderPlaced($id, $customerId, new \DateTimeImmutable()));

        return $order;
    }

    public function addItem(ProductId $productId, int $quantity, Money $unitPrice): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw InvalidOrderStateTransitionException::notAllowedInState('addItem', $this->status->value);
        }

        $this->assertSameCurrency($unitPrice);

        // Jedna položka na produkt: množství se sčítá, položka se neduplikuje.
        foreach ($this->lines as $existing) {
            if ($existing->productId->equals($productId)) {
                $existing->increaseQuantity($quantity);
                $this->record(new OrderItemAdded($this->id, $productId, $quantity, new \DateTimeImmutable()));

                return;
            }
        }

        $this->lines->add(new OrderItem($this, $productId, $quantity, $unitPrice));
        $this->record(new OrderItemAdded($this->id, $productId, $quantity, new \DateTimeImmutable()));
    }

    public function changeItemQuantity(ProductId $productId, int $quantity): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw InvalidOrderStateTransitionException::notAllowedInState('changeItemQuantity', $this->status->value);
        }

        if ($quantity < 1) {
            throw InvalidQuantityException::mustBePositive($quantity);
        }

        $this->itemFor($productId)->changeQuantity($quantity);
        $this->record(new OrderItemQuantityChanged($this->id, $productId, $quantity, new \DateTimeImmutable()));
    }

    public function removeItem(ProductId $productId): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw InvalidOrderStateTransitionException::notAllowedInState('removeItem', $this->status->value);
        }

        $item = $this->itemFor($productId);
        if ($this->lines->count() === 1) {
            throw LastItemCannotBeRemovedException::forProduct($productId);
        }

        // Sleva nesmí po odebrání přesáhnout nový součet položek.
        $newTotal = $this->totalAmount()->subtract($item->subtotal());
        if ($this->discount->amountInCents > $newTotal->amountInCents) {
            throw DiscountExceedsItemsTotalException::forItemsTotal($this->discount, $newTotal);
        }

        $this->lines->removeElement($item);
        $this->record(new OrderItemRemoved($this->id, $productId, new \DateTimeImmutable()));
    }

    public function applyDiscount(Money $discount): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw InvalidOrderStateTransitionException::notAllowedInState('applyDiscount', $this->status->value);
        }

        $this->assertSameCurrency($discount);

        $total = $this->totalAmount();
        if ($discount->amountInCents > $total->amountInCents) {
            throw DiscountExceedsItemsTotalException::forItemsTotal($discount, $total);
        }

        $this->discount = $discount;
    }

    public function confirm(?\DateTimeImmutable $at = null): void
    {
        if (!$this->status->canTransitionTo(OrderStatus::Confirmed)) {
            throw InvalidOrderStateTransitionException::cannotTransition(
                $this->status->value,
                OrderStatus::Confirmed->value,
            );
        }

        if ($this->lines->isEmpty()) {
            throw EmptyOrderException::cannotConfirm();
        }

        $this->status = OrderStatus::Confirmed;
        $this->placedAt = $at ?? new \DateTimeImmutable();
        $this->record(new OrderConfirmed($this->id, $this->customerId, $this->placedAt));
    }

    public function markPaid(): void
    {
        // Opakované doručení příkazu o platbě není chyba volajícího.
        if ($this->status === OrderStatus::Paid) {
            return;
        }

        if (!$this->status->canTransitionTo(OrderStatus::Paid)) {
            throw InvalidOrderStateTransitionException::cannotTransition(
                $this->status->value,
                OrderStatus::Paid->value,
            );
        }

        $this->status = OrderStatus::Paid;
        $this->record(new OrderPaid($this->id, new \DateTimeImmutable()));
    }

    public function ship(): void
    {
        if ($this->status === OrderStatus::Shipped) {
            return;
        }

        if (!$this->status->canTransitionTo(OrderStatus::Shipped)) {
            throw InvalidOrderStateTransitionException::cannotTransition(
                $this->status->value,
                OrderStatus::Shipped->value,
            );
        }

        $this->status = OrderStatus::Shipped;
        $this->record(new OrderShipped($this->id, new \DateTimeImmutable()));
    }

    public function deliver(): void
    {
        if (!$this->status->canTransitionTo(OrderStatus::Delivered)) {
            throw InvalidOrderStateTransitionException::cannotTransition(
                $this->status->value,
                OrderStatus::Delivered->value,
            );
        }

        $this->status = OrderStatus::Delivered;
        $this->record(new OrderDelivered($this->id, new \DateTimeImmutable()));
    }

    /**
     * Čas přichází zvenku, aby šel v testech zadat.
     *
     * Vrací částku, kterou je třeba zákazníkovi vrátit.
     */
    public function cancel(string $reason, \DateTimeImmutable $when): Money
    {
        // Opakované storno není chyba volajícího, jen už není co dělat ani vracet.
        if ($this->status === OrderStatus::Cancelled) {
            return Money::zero($this->currency);
        }

        // Storno je hrana grafu jako každá jiná: odeslanou ani doručenou
        // objednávku zpátky nevrátí.
        if (!$this->status->canTransitionTo(OrderStatus::Cancelled)) {
            throw InvalidOrderStateTransitionException::cannotTransition(
                $this->status->value,
                OrderStatus::Cancelled->value,
            );
        }

        // Peníze vracíme jen za zaplacenou objednávku, a to přesně tolik, kolik zaplatila.
        $refund = $this->status === OrderStatus::Paid
            ? $this->paidAmount()
            : Money::zero($this->currency);

        $this->status = OrderStatus::Cancelled;
        $this->record(new OrderCancelled($this->id, $this->customerId, $reason, $when, $refund));

        return $refund;
    }

    public function isOwnedBy(CustomerId $customerId): bool
    {
        return $this->customerId->equals($customerId);
    }

    /** Součet položek před slevou. */
    public function totalAmount(): Money
    {
        $total = Money::zero($this->currency);
        foreach ($this->lines as $item) {
            $total = $total->add($item->subtotal());
        }

        return $total;
    }

    /** Částka, kterou zákazník platí: součet položek po slevě. */
    public function paidAmount(): Money
    {
        return $this->totalAmount()
            ->subtract($this->discount);
    }

    private function itemFor(ProductId $productId): OrderItem
    {
        foreach ($this->lines as $item) {
            if ($item->productId->equals($productId)) {
                return $item;
            }
        }

        throw OrderItemNotFoundException::forProduct($productId);
    }

    private function assertSameCurrency(Money $amount): void
    {
        if ($amount->currency !== $this->currency) {
            throw CurrencyMismatchException::forOrder($this->currency, $amount->currency);
        }
    }
}
