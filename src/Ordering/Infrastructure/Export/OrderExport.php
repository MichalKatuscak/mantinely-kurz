<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Export;

use App\Ordering\Domain\Model\Order;

/**
 * CSV s objednávkami zákazníka (tlačítko „Stáhnout CSV“ v přehledu).
 */
final class OrderExport
{
    /** @param list<Order> $orders */
    public function toCsv(array $orders): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            throw new \RuntimeException('Cannot open temporary stream.');
        }

        fputcsv($out, ['order', 'status', 'product', 'quantity', 'unit_price', 'subtotal', 'items_total', 'discount', 'paid'], escape: '');
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $line = InvoiceLine::fromItem($item);
                fputcsv($out, [
                    $order->id->value,
                    $order->status->value,
                    $line->productId,
                    $line->quantity,
                    $this->format($line->priceInCents()),
                    $this->format($line->subtotalInCents()),
                    $this->format($order->totalAmount()->amountInCents),
                    $this->format($order->discount->amountInCents),
                    $this->format($this->cents($order->paidAmount())),
                ], escape: '');
            }
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv === false ? '' : $csv;
    }

    private function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    // Starší pomocná metoda bez typu, zůstala z prvního exportu.
    private function cents($money): int
    {
        return $money->getAmountInCents();
    }
}
