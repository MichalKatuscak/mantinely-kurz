<?php
/**
 * Storno objednavky z detailu: stav, vraceni penez zakaznikovi a uvolneni skladu.
 *
 * Stornovat lze draft, confirmed, paid a shipped (vracena zasilka, viz orders.php),
 * ne delivered. Vraci se castka po sleve (jako order_total_after_discount()),
 * a jen u objednavek, ktere byly zaplacene (paid, shipped).
 */

namespace App\Legacy\lib;

class OrderCancellation
{
    /**
     * @return array|string pole array('refund' => halere, 'currency' => ..) nebo text chyby
     */
    public static function cancel($orderId, $reason = '')
    {
        global $db;
        legacy_db();

        $order = $db->one("SELECT * FROM orders WHERE id = " . $db->quote($orderId));
        if ($order === null) {
            return 'Objednávka neexistuje';
        }
        if ($order['status'] == 'cancelled') {
            return 'Objednávka už je stornovaná';
        }
        if ($order['status'] == 'delivered') {
            return 'Doručenou objednávku nelze stornovat';
        }

        $refund = in_array($order['status'], array('paid', 'shipped'))
            ? order_total_after_discount($orderId)
            : 0;

        $db->begin();
        try {
            $db->exec("UPDATE orders SET status = 'cancelled' WHERE id = " . $db->quote($orderId));
            $released = StockReport::releaseReservations($orderId);
            audit_log('order', $orderId, 'storno', array(
                'from'     => $order['status'],
                'reason'   => $reason,
                'refund'   => $refund,
                'currency' => $order['currency'],
                'released' => $released,
            ));
            $db->commit();
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }

        cache_delete('dashboard_stats');

        $customer = $db->one("SELECT email FROM customers WHERE id = " . $db->quote($order['customer_id']));
        if ($customer) {
            $body = "Dobrý den,\n\nvaše objednávka " . $orderId . " byla stornována.\n";
            if ($refund > 0) {
                $body .= "Zaplacenou částku " . format_price($refund, $order['currency']) . " vám vrátíme.\n";
            }
            send_mail($customer['email'], 'Objednávka byla stornována', $body);
        }

        return array('refund' => $refund, 'currency' => $order['currency']);
    }
}
