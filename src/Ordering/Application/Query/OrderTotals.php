<?php

declare(strict_types=1);

namespace App\Ordering\Application\Query;

use App\Ordering\Domain\Model\Order;
use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;

/**
 * Souhrn pro přehled objednávek zákazníka.
 */
final readonly class OrderTotals
{
    public function __construct(
        public int $count,
        public Money $itemsTotal,
        public Money $discounts,
        public Money $paid,
    ) {}

    /** @param list<Order> $orders */
    public static function of(array $orders, Currency $currency = Currency::CZK): self
    {
        $itemsTotal = 0;
        $discounts = 0;
        $paid = 0;
        foreach ($orders as $order) {
            if ($order->currency !== $currency) {
                continue;
            }
            $itemsTotal += $order->totalAmount()->amountInCents;
            $discounts += $order->discount->amountInCents;
            $paid += $order->paidAmount()->amountInCents;
        }

        return new self(
            count($orders),
            new Money($itemsTotal, $currency),
            new Money($discounts, $currency),
            new Money($paid, $currency),
        );
    }

    /**
     * Součet částek, které přišly odjinud (např. z cache přehledu).
     *
     * @param array<mixed> $amounts
     */
    public static function sumOf(array $amounts, Currency $currency = Currency::CZK): Money
    {
        $sum = 0;
        foreach ($amounts as $amount) {
            $sum += $amount->getAmountInCents();
        }

        return new Money($sum, $currency);
    }
}
