<?php

declare(strict_types=1);

namespace App\Legacy\Report;

use App\Legacy\lib\LegacyDb;

/**
 * Tržby za měsíc pro report staré administrace, s typy a bez globálního stavu.
 *
 * Počítá záměrně stejně jako dřívější funkce monthlyRevenue() v lib/revenue.php:
 * jen zaplacené objednávky (stav paid), bez slevy na objednávku, cizí měny přes
 * kurz v exchange_rates. Chování hlídají charakterizační testy v tests/Legacy.
 */
final readonly class MonthlyRevenue
{
    public function __construct(
        private LegacyDb $db,
    ) {}

    /** Tržby v CZK naformátované jako „1 200,00“. */
    public function forMonth(string $month): string
    {
        $sum = 0;
        foreach ($this->paidItems($month) as $item) {
            $line = $item['unitPrice'] * $item['quantity'];
            if ($item['currency'] !== 'CZK') {
                $line = $this->toCzk($line, $item['currency']);
            }
            $sum += $line;
        }

        // Haléře na koruny, zaokrouhlení na dvě místa jako dřív.
        $sum = round($sum / 100, 2);

        return number_format($sum, 2, ',', ' ');
    }

    /** @return list<array{unitPrice: int, quantity: int, currency: string}> */
    private function paidItems(string $month): array
    {
        $rows = $this->db->query(
            'SELECT i.unit_price_amount_in_cents AS unitPrice, i.quantity AS quantity, i.unit_price_currency AS currency'
            .' FROM order_items i JOIN orders o ON o.id = i.order_id'
            ." WHERE o.status = 'paid' AND strftime('%Y-%m', o.placed_at) = ".$this->db->quote($month),
        );

        $items = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || !is_numeric($row['unitPrice'] ?? null) || !is_numeric($row['quantity'] ?? null) || !is_string($row['currency'] ?? null)) {
                throw new \UnexpectedValueException('Unexpected row in order_items.');
            }
            $items[] = ['unitPrice' => (int) $row['unitPrice'], 'quantity' => (int) $row['quantity'], 'currency' => $row['currency']];
        }

        return $items;
    }

    private function toCzk(int|float $cents, string $currency): int|float
    {
        $rate = $this->db->value('SELECT rate_to_czk FROM exchange_rates WHERE currency = '.$this->db->quote($currency));

        // Neznámá měna se bere jako CZK (stejně jako dřív).
        return is_numeric($rate) ? $cents * (float) $rate : $cents;
    }
}
