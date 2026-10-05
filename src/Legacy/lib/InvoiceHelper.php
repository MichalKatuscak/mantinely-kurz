<?php
/**
 * Vystavovani faktur k objednavkam.
 *
 * Cislo faktury: FV + rok + poradi (FV2017000123). Poradi se bere z MAX(),
 * takze pri dvou soubeznych vystavenich muze vzniknout stejne cislo. FIXME
 */

namespace App\Legacy\lib;

class InvoiceHelper
{
    public static function nextNumber($year = null)
    {
        global $db;
        legacy_db();
        if ($year === null) {
            $year = date('Y');
        }
        $prefix = config('invoice_prefix', 'FV') . $year;
        $row = $db->one("SELECT MAX(number) AS m FROM invoices WHERE number LIKE '" . $prefix . "%'");
        $n = 1;
        if ($row !== null && $row['m'] !== null) {
            $n = ((int) substr($row['m'], strlen($prefix))) + 1;
        }

        return $prefix . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Vystavi fakturu k objednavce. Vraci ID faktury nebo false.
     */
    public static function issue($orderId)
    {
        global $db;
        legacy_db();

        $order = $db->one("SELECT * FROM orders WHERE id = '" . $orderId . "'");
        if ($order === null) {
            return false;
        }
        // faktura jen pro zaplacene a dal
        if (!in_array($order['status'], array('paid', 'shipped', 'delivered'))) {
            return false;
        }
        $existing = $db->one("SELECT id FROM invoices WHERE order_id = '" . $orderId . "'");
        if ($existing) {
            return $existing['id'];
        }

        $total = order_total_after_discount($orderId);
        $number = self::nextNumber();
        $db->exec("INSERT INTO invoices (order_id, number, issued_at, total_cents, currency) VALUES ('"
            . $orderId . "', '" . $number . "', '" . date('Y-m-d H:i:s') . "', " . (int) $total . ", '" . $order['currency'] . "')");
        $id = $db->lastId();
        audit_log('invoice', $id, 'issue', array('order' => $orderId, 'number' => $number));

        return $id;
    }

    public static function load($id)
    {
        global $db;
        legacy_db();

        return $db->one("SELECT * FROM invoices WHERE id = " . (int) $id);
    }

    public static function items($orderId)
    {
        global $db;
        legacy_db();

        return $db->query("SELECT i.*, COALESCE(p.name, i.product_id) AS name FROM order_items i LEFT JOIN products p ON p.id = i.product_id WHERE i.order_id = '" . $orderId . "' ORDER BY i.id");
    }

    /**
     * Rozpis DPH pro fakturu (jen jedna sazba).
     */
    public static function vatSummary($invoice)
    {
        $total = (int) $invoice['total_cents'];

        return array(
            'base'  => PriceUtils::withoutVat($total),
            'vat'   => PriceUtils::vatPart($total),
            'total' => $total,
            'rate'  => PriceUtils::VAT,
        );
    }

    /**
     * Dobropis – zaporna faktura. Rozdelane, nikdy nedokonceno (2018).
     */
    public static function creditNote($invoiceId)
    {
        // TODO dobropis: vystavit fakturu se zapornou castkou, navazat na puvodni
        return false;
    }
}
