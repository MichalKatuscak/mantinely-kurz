<?php

declare(strict_types=1);

namespace App\Tests\Ordering\Domain;

use App\Ordering\Domain\Event\OrderCancelled;
use App\Ordering\Domain\Event\OrderConfirmed;
use App\Ordering\Domain\Event\OrderItemAdded;
use App\Ordering\Domain\Event\OrderItemRemoved;
use App\Ordering\Domain\Event\OrderPaid;
use App\Ordering\Domain\Event\OrderPlaced;
use App\Ordering\Domain\Exception\CurrencyMismatchException;
use App\Ordering\Domain\Exception\DiscountExceedsItemsTotalException;
use App\Ordering\Domain\Exception\EmptyOrderException;
use App\Ordering\Domain\Exception\InvalidOrderStateTransitionException;
use App\Ordering\Domain\Exception\InvalidQuantityException;
use App\Ordering\Domain\Exception\LastItemCannotBeRemovedException;
use App\Ordering\Domain\Exception\OrderItemNotFoundException;
use App\Ordering\Domain\Model\Order;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\OrderStatus;
use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Order::class)]
final class OrderTest extends TestCase
{
    #[Test]
    public function placedOrderIsDraftAndRecordsOrderPlaced(): void
    {
        $order = Order::place(OrderId::generate(), CustomerId::generate());

        self::assertSame(OrderStatus::Draft, $order->status);
        $events = $order->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderPlaced::class, $events[0]);
    }

    #[Test]
    public function sameProductIsAddedToExistingItem(): void
    {
        $order = $this->draftOrder();
        $order->releaseEvents();
        $productId = ProductId::generate();

        $order->addItem($productId, 1, $this->czk(500_00));
        $order->addItem($productId, 2, $this->czk(500_00));

        self::assertCount(1, $order->items);
        self::assertSame(3, $order->items[0]->quantity);
        self::assertContainsOnlyInstancesOf(OrderItemAdded::class, $order->releaseEvents());
    }

    #[Test]
    public function itemCannotBeAddedToConfirmedOrder(): void
    {
        $order = $this->confirmedOrder();

        $this->expectException(InvalidOrderStateTransitionException::class);
        $order->addItem(ProductId::generate(), 1, $this->czk(100_00));
    }

    #[Test]
    public function itemInOtherCurrencyIsRejected(): void
    {
        $order = $this->draftOrder();

        $this->expectException(CurrencyMismatchException::class);
        $order->addItem(ProductId::generate(), 1, new Money(100_00, Currency::EUR));
    }

    #[Test]
    public function itemQuantityChangesInDraftOrder(): void
    {
        $order = $this->draftOrder();
        $productId = ProductId::generate();
        $order->addItem($productId, 2, $this->czk(300_00));

        $order->changeItemQuantity($productId, 5);

        self::assertSame(5, $order->items[0]->quantity);
        self::assertSame(1500_00, $order->totalAmount()->amountInCents);
    }

    #[Test]
    public function itemQuantityCannotChangeInConfirmedOrder(): void
    {
        $order = $this->confirmedOrder();

        $this->expectException(InvalidOrderStateTransitionException::class);
        $order->changeItemQuantity($order->items[0]->productId, 1);
    }

    #[Test]
    public function itemQuantityMustBePositive(): void
    {
        $order = $this->draftOrder();
        $productId = ProductId::generate();
        $order->addItem($productId, 2, $this->czk(300_00));

        $this->expectException(InvalidQuantityException::class);
        $order->changeItemQuantity($productId, 0);
    }

    #[Test]
    public function quantityOfMissingItemCannotChange(): void
    {
        $order = $this->draftOrder();

        $this->expectException(OrderItemNotFoundException::class);
        $order->changeItemQuantity(ProductId::generate(), 1);
    }

    #[Test]
    public function itemIsRemovedFromDraftOrder(): void
    {
        $order = $this->draftOrder();
        $keyboard = ProductId::generate();
        $mouse = ProductId::generate();
        $order->addItem($keyboard, 2, $this->czk(300_00));
        $order->addItem($mouse, 1, $this->czk(400_00));
        $order->releaseEvents();

        $order->removeItem($mouse);

        self::assertCount(1, $order->items);
        self::assertTrue($order->items[0]->productId->equals($keyboard));
        $events = $order->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderItemRemoved::class, $events[0]);
    }

    #[Test]
    public function itemCannotBeRemovedFromConfirmedOrder(): void
    {
        $order = $this->draftOrder();
        $keyboard = ProductId::generate();
        $order->addItem($keyboard, 2, $this->czk(300_00));
        $order->addItem(ProductId::generate(), 1, $this->czk(400_00));
        $order->confirm();

        $this->expectException(InvalidOrderStateTransitionException::class);
        $order->removeItem($keyboard);
    }

    #[Test]
    public function lastItemCannotBeRemoved(): void
    {
        $order = $this->draftOrder();
        $keyboard = ProductId::generate();
        $order->addItem($keyboard, 2, $this->czk(300_00));

        $this->expectException(LastItemCannotBeRemovedException::class);
        $order->removeItem($keyboard);
    }

    #[Test]
    public function paidAmountDropsAfterItemRemoval(): void
    {
        // 2 × 300 + 1 × 400 = 1 000 Kč, sleva 100 Kč → 900 Kč; bez myši 600 − 100 = 500 Kč
        $order = $this->draftOrder();
        $mouse = ProductId::generate();
        $order->addItem(ProductId::generate(), 2, $this->czk(300_00));
        $order->addItem($mouse, 1, $this->czk(400_00));
        $order->applyDiscount($this->czk(100_00));

        $order->removeItem($mouse);

        self::assertSame(600_00, $order->totalAmount()->amountInCents);
        self::assertSame(500_00, $order->paidAmount()->amountInCents);
    }

    #[Test]
    public function itemCannotBeRemovedWhenDiscountWouldExceedNewTotal(): void
    {
        $order = $this->draftOrder();
        $mouse = ProductId::generate();
        $order->addItem(ProductId::generate(), 1, $this->czk(300_00));
        $order->addItem($mouse, 1, $this->czk(400_00));
        $order->applyDiscount($this->czk(500_00));

        $this->expectException(DiscountExceedsItemsTotalException::class);
        $order->removeItem($mouse);
    }

    #[Test]
    public function itemCanBeRemovedWhenDiscountEqualsNewTotal(): void
    {
        $order = $this->draftOrder();
        $mouse = ProductId::generate();
        $order->addItem(ProductId::generate(), 1, $this->czk(300_00));
        $order->addItem($mouse, 1, $this->czk(400_00));
        $order->applyDiscount($this->czk(300_00));

        $order->removeItem($mouse);

        self::assertSame(0, $order->paidAmount()->amountInCents);
    }

    #[Test]
    public function emptyOrderCannotBeConfirmed(): void
    {
        $order = $this->draftOrder();

        $this->expectException(EmptyOrderException::class);
        $order->confirm();
    }

    #[Test]
    public function confirmedOrderRecordsOrderConfirmed(): void
    {
        $order = $this->draftOrder();
        $order->addItem(ProductId::generate(), 1, $this->czk(100_00));
        $order->releaseEvents();

        $order->confirm(new \DateTimeImmutable('2026-03-10 10:00'));

        self::assertSame(OrderStatus::Confirmed, $order->status);
        self::assertEquals(new \DateTimeImmutable('2026-03-10 10:00'), $order->placedAt);
        $events = $order->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderConfirmed::class, $events[0]);
    }

    #[Test]
    public function draftOrderCannotBePaid(): void
    {
        $order = $this->draftOrder();

        $this->expectException(InvalidOrderStateTransitionException::class);
        $order->markPaid();
    }

    #[Test]
    public function secondPaymentRecordsNothing(): void
    {
        $order = $this->paidOrder();
        $order->releaseEvents();

        $order->markPaid();

        self::assertSame(OrderStatus::Paid, $order->status);
        self::assertSame([], $order->releaseEvents());
    }

    #[Test]
    public function paidOrderRecordsOrderPaid(): void
    {
        $order = $this->confirmedOrder();
        $order->releaseEvents();

        $order->markPaid();

        $events = $order->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderPaid::class, $events[0]);
    }

    #[Test]
    public function confirmedOrderCannotBeShipped(): void
    {
        $order = $this->confirmedOrder();

        $this->expectException(InvalidOrderStateTransitionException::class);
        $order->ship();
    }

    #[Test]
    public function shippedOrderCanBeDelivered(): void
    {
        $order = $this->shippedOrder();

        $order->deliver();

        self::assertSame(OrderStatus::Delivered, $order->status);
    }

    #[Test]
    public function paidOrderCanBeCancelled(): void
    {
        $order = $this->paidOrder();
        $order->releaseEvents();

        $order->cancel('customer request', new \DateTimeImmutable());

        self::assertSame(OrderStatus::Cancelled, $order->status);
        $events = $order->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderCancelled::class, $events[0]);
    }

    #[Test]
    public function draftOrderCanBeCancelled(): void
    {
        $order = $this->draftOrder();

        $order->cancel('customer request', new \DateTimeImmutable());

        self::assertSame(OrderStatus::Cancelled, $order->status);
    }

    #[Test]
    public function shippedOrderCannotBeCancelled(): void
    {
        $order = $this->shippedOrder();
        $order->releaseEvents();

        try {
            $order->cancel('customer request', new \DateTimeImmutable());
            self::fail('Expected InvalidOrderStateTransitionException');
        } catch (InvalidOrderStateTransitionException) {
        }
        self::assertSame(OrderStatus::Shipped, $order->status);
        self::assertSame([], $order->releaseEvents());
    }

    #[Test]
    public function secondCancelRecordsNothing(): void
    {
        $order = $this->paidOrder();
        $order->cancel('customer request', new \DateTimeImmutable());
        $order->releaseEvents();

        $order->cancel('customer request', new \DateTimeImmutable());

        self::assertSame(OrderStatus::Cancelled, $order->status);
        self::assertSame([], $order->releaseEvents());
    }

    #[Test]
    public function cancelledPaidOrderRefundsPaidAmountAfterDiscount(): void
    {
        $order = $this->draftOrder();
        $order->addItem(ProductId::generate(), 2, $this->czk(500_00));
        $order->applyDiscount($this->czk(150_00));
        $order->confirm();
        $order->markPaid();
        $order->releaseEvents();

        $refund = $order->cancel('customer request', new \DateTimeImmutable());

        self::assertTrue($refund->equals($this->czk(850_00)));
        $events = $order->releaseEvents();
        self::assertInstanceOf(OrderCancelled::class, $events[0]);
        self::assertTrue($events[0]->refund->equals($this->czk(850_00)));
    }

    #[Test]
    public function cancelledUnpaidOrderRefundsNothing(): void
    {
        $order = $this->confirmedOrder();

        $refund = $order->cancel('customer request', new \DateTimeImmutable());

        self::assertTrue($refund->equals(Money::zero(Currency::CZK)));
    }

    #[Test]
    public function secondCancelRefundsNothing(): void
    {
        $order = $this->paidOrder();
        $order->cancel('customer request', new \DateTimeImmutable());

        $refund = $order->cancel('customer request', new \DateTimeImmutable());

        self::assertTrue($refund->equals(Money::zero(Currency::CZK)));
    }

    #[Test]
    public function paidAmountIsItemsTotalAfterDiscount(): void
    {
        $order = $this->draftOrder();
        $order->addItem(ProductId::generate(), 2, $this->czk(300_00));
        $order->addItem(ProductId::generate(), 1, $this->czk(400_00));

        $order->applyDiscount($this->czk(100_00));

        self::assertSame(1000_00, $order->totalAmount()->amountInCents);
        self::assertSame(900_00, $order->paidAmount()->amountInCents);
    }

    #[Test]
    public function discountUpToItemsTotalIsAccepted(): void
    {
        $order = $this->orderWithItemsTotal(1000_00);
        $order->applyDiscount(new Money(100_00, Currency::CZK));
        self::assertSame(100_00, $order->discount->amountInCents);
    }

    #[Test]
    public function discountEqualToItemsTotalIsAccepted(): void
    {
        $order = $this->orderWithItemsTotal(1000_00);
        $order->applyDiscount(new Money(1000_00, Currency::CZK));
        self::assertSame(0, $order->paidAmount()->amountInCents);
    }

    #[Test]
    public function discountAboveItemsTotalIsRejected(): void
    {
        $order = $this->orderWithItemsTotal(1000_00);
        $this->expectException(DiscountExceedsItemsTotalException::class);
        $order->applyDiscount(new Money(1100_00, Currency::CZK));
    }

    #[Test]
    public function discountCannotChangeAfterConfirmation(): void
    {
        $order = $this->confirmedOrder();

        $this->expectException(InvalidOrderStateTransitionException::class);
        $order->applyDiscount($this->czk(100_00));
    }

    #[Test]
    public function orderIsOwnedByCustomerWhoPlacedIt(): void
    {
        $customerId = CustomerId::generate();
        $order = Order::place(OrderId::generate(), $customerId);

        self::assertTrue($order->isOwnedBy($customerId));
        self::assertFalse($order->isOwnedBy(CustomerId::generate()));
    }

    private function draftOrder(): Order
    {
        return Order::place(OrderId::generate(), CustomerId::generate());
    }

    private function orderWithItemsTotal(int $amountInCents): Order
    {
        $order = $this->draftOrder();
        $order->addItem(ProductId::generate(), 1, $this->czk($amountInCents));

        return $order;
    }

    private function confirmedOrder(): Order
    {
        $order = $this->draftOrder();
        $order->addItem(ProductId::generate(), 2, $this->czk(500_00));
        $order->confirm();

        return $order;
    }

    private function paidOrder(): Order
    {
        $order = $this->confirmedOrder();
        $order->markPaid();

        return $order;
    }

    private function shippedOrder(): Order
    {
        $order = $this->paidOrder();
        $order->ship();

        return $order;
    }

    private function czk(int $amountInCents): Money
    {
        return new Money($amountInCents, Currency::CZK);
    }
}
