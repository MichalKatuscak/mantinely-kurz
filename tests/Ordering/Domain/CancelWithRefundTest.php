<?php

declare(strict_types=1);

namespace App\Tests\Ordering\Domain;

use App\Ordering\Domain\Event\OrderCancelled;
use App\Ordering\Domain\Event\RefundRequested;
use App\Ordering\Domain\Exception\InvalidOrderStateTransitionException;
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
final class CancelWithRefundTest extends TestCase
{
    #[Test]
    public function paidOrderRefundsPaidAmount(): void
    {
        $order = $this->paidOrder();
        $order->releaseEvents();
        $when = new \DateTimeImmutable('2026-10-08 10:00');

        $order->cancelWithRefund('Zákazník si to rozmyslel', $when);

        self::assertSame(OrderStatus::Cancelled, $order->status);
        self::assertSame('Zákazník si to rozmyslel', $order->cancellationNote);
        // 2 × 300 Kč − sleva 100 Kč
        self::assertTrue($order->refund->equals(new Money(500_00, Currency::CZK)));

        $events = $order->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(OrderCancelled::class, $events[0]);
        self::assertInstanceOf(RefundRequested::class, $events[1]);
        self::assertTrue($events[1]->orderId->equals($order->id));
        self::assertTrue($events[1]->customerId->equals($order->customerId));
        self::assertTrue($events[1]->amount->equals(new Money(500_00, Currency::CZK)));
        self::assertSame($when, $events[1]->occurredAt);
    }

    #[Test]
    public function unpaidOrderIsCancelledWithoutRefund(): void
    {
        $order = $this->confirmedOrder();
        $order->releaseEvents();

        $order->cancelWithRefund('', new \DateTimeImmutable());

        self::assertSame(OrderStatus::Cancelled, $order->status);
        self::assertSame(0, $order->refund->amountInCents);
        $events = $order->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderCancelled::class, $events[0]);
    }

    #[Test]
    public function secondCancelDoesNotRefundAgain(): void
    {
        $order = $this->paidOrder();
        $order->cancelWithRefund('', new \DateTimeImmutable());
        $order->releaseEvents();

        $order->cancelWithRefund('', new \DateTimeImmutable());

        self::assertSame(500_00, $order->refund->amountInCents);
        self::assertSame([], $order->releaseEvents());
    }

    #[Test]
    public function shippedOrderCannotBeCancelledNorRefunded(): void
    {
        $order = $this->paidOrder();
        $order->ship();

        try {
            $order->cancelWithRefund('', new \DateTimeImmutable());
            self::fail('Expected InvalidOrderStateTransitionException');
        } catch (InvalidOrderStateTransitionException) {
        }

        self::assertSame(OrderStatus::Shipped, $order->status);
        self::assertSame(0, $order->refund->amountInCents);
    }

    #[Test]
    public function newOrderHasNothingToRefund(): void
    {
        $order = Order::place(OrderId::generate(), CustomerId::generate(), Currency::EUR);

        self::assertTrue($order->refund->equals(Money::zero(Currency::EUR)));
    }

    private function confirmedOrder(): Order
    {
        $order = Order::place(OrderId::generate(), CustomerId::generate());
        $order->addItem(ProductId::generate(), 2, new Money(300_00, Currency::CZK));
        $order->applyDiscount(new Money(100_00, Currency::CZK));
        $order->confirm();

        return $order;
    }

    private function paidOrder(): Order
    {
        $order = $this->confirmedOrder();
        $order->markPaid();

        return $order;
    }
}
