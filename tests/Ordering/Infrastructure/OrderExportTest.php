<?php

declare(strict_types=1);

namespace App\Tests\Ordering\Infrastructure;

use App\Ordering\Domain\Model\Order;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;
use App\Ordering\Infrastructure\Export\InvoiceLine;
use App\Ordering\Infrastructure\Export\OrderExport;
use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderExport::class)]
#[CoversClass(InvoiceLine::class)]
final class OrderExportTest extends TestCase
{
    #[Test]
    public function exportsOneRowPerItemWithOrderAmounts(): void
    {
        $order = Order::place(OrderId::generate(), CustomerId::generate());
        $order->addItem(ProductId::generate(), 2, new Money(300_00, Currency::CZK));
        $order->addItem(ProductId::generate(), 1, new Money(400_00, Currency::CZK));
        $order->applyDiscount(new Money(100_00, Currency::CZK));

        $rows = array_map(
            static fn (string $row): array => str_getcsv($row, escape: ''),
            explode("\n", trim((new OrderExport())->toCsv([$order]))),
        );

        self::assertCount(3, $rows);
        self::assertSame(['300.00', '600.00', '1000.00', '100.00', '900.00'], array_slice($rows[1], 4));
        self::assertSame(['400.00', '400.00', '1000.00', '100.00', '900.00'], array_slice($rows[2], 4));
        self::assertSame(1000_00, $order->totalAmount()->getAmountInCents());
        self::assertSame(900_00, $order->paidAmount()->getAmountInCents());
    }

    #[Test]
    public function invoiceLineComputesSubtotal(): void
    {
        $line = new InvoiceLine('abc', 3, new Money(250_00, Currency::CZK));

        self::assertSame(250_00, $line->priceInCents());
        self::assertSame(750_00, $line->subtotalInCents());
        self::assertSame(250_00, $line->price->getAmountInCents());
    }

    #[Test]
    public function invoiceLineCanBeBuiltFromArray(): void
    {
        $line = InvoiceLine::fromArray(['productId' => 'abc', 'quantity' => 2, 'price' => new Money(120_00, Currency::CZK)]);

        self::assertSame(2, $line->quantity);
        self::assertSame(120_00, $line->price->getAmountInCents());
        self::assertSame(240_00, $line->subtotalInCents());
    }
}
