<?php

declare(strict_types=1);

namespace App\Tests\Ordering\Application;

use App\Ordering\Application\Query\OrderTotals;
use App\Ordering\Domain\Model\Order;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderTotals::class)]
final class OrderTotalsTest extends TestCase
{
    #[Test]
    public function sumsItemsDiscountsAndPaidAmounts(): void
    {
        $totals = OrderTotals::of([
            $this->order(1000_00, 100_00),
            $this->order(500_00, 0),
        ]);

        self::assertSame(2, $totals->count);
        self::assertSame(1500_00, $totals->itemsTotal->getAmountInCents());
        self::assertSame(100_00, $totals->discounts->getAmountInCents());
        self::assertSame(1400_00, $totals->paid->getAmountInCents());
    }

    #[Test]
    public function skipsOrdersInOtherCurrency(): void
    {
        $euroOrder = Order::place(OrderId::generate(), CustomerId::generate(), Currency::EUR);
        $euroOrder->addItem(ProductId::generate(), 1, new Money(10_00, Currency::EUR));

        $totals = OrderTotals::of([$this->order(1000_00, 0), $euroOrder]);

        self::assertSame(1000_00, $totals->itemsTotal->getAmountInCents());
        self::assertSame(0, $totals->discounts->getAmountInCents());
        self::assertSame(1000_00, $totals->paid->getAmountInCents());
    }

    #[Test]
    public function sumsAmountsFromOtherSources(): void
    {
        $sum = OrderTotals::sumOf([new Money(100_00, Currency::CZK), new Money(250_00, Currency::CZK)]);

        self::assertSame(350_00, $sum->getAmountInCents());
    }

    private function order(int $itemsTotal, int $discount): Order
    {
        $order = Order::place(OrderId::generate(), CustomerId::generate());
        $order->addItem(ProductId::generate(), 1, new Money($itemsTotal, Currency::CZK));
        $order->applyDiscount(new Money($discount, Currency::CZK));

        return $order;
    }
}
