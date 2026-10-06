<?php
/**
 * Report skladu. Cte tabulku stock_items (od 2024 ji spravuje novy e-shop),
 * rezervace jsou JSON {"orderId": pocet}.
 */

namespace App\Legacy\lib;

class StockReport
{
    public static function all()
    {
        global $db;
        legacy_db();
        $rows = $db->query("SELECT s.product_id, s.on_hand, s.reservations, p.name, p.sku, p.active"
            . " FROM stock_items s LEFT JOIN products p ON p.id = s.product_id ORDER BY p.name");

        $out = array();
        foreach ($rows as $r) {
            $reserved = self::reservedQty($r['reservations']);
            $r['reserved'] = $reserved;
            $r['available'] = (int) $r['on_hand'] - $reserved;
            $r['low'] = $r['available'] < config('low_stock', 5);
            $out[] = $r;
        }

        return $out;
    }

    /**
     * Soucet rezervaci z JSONu.
     */
    public static function reservedQty($json)
    {
        if ($json === null || $json === '') {
            return 0;
        }
        $data = json_decode((string) $json, true);
        if (!is_array($data)) {
            return 0;
        }
        $sum = 0;
        foreach ($data as $orderId => $qty) {
            $sum += (int) $qty;
        }

        return $sum;
    }

    /**
     * Rezervace s cisly objednavek (pro detail produktu).
     */
    public static function reservations($productId)
    {
        global $db;
        legacy_db();
        $r = $db->one("SELECT reservations FROM stock_items WHERE product_id = '" . $productId . "'");
        if ($r === null) {
            return array();
        }
        $data = json_decode((string) $r['reservations'], true);

        return is_array($data) ? $data : array();
    }

    public static function lowStock()
    {
        $out = array();
        foreach (self::all() as $r) {
            if ($r['low']) {
                $out[] = $r;
            }
        }

        return $out;
    }

    /**
     * Rucni korekce skladu (inventura). Prepise on_hand primo v tabulce.
     * FIXME: novy e-shop o tom nevi, ale funguje to
     */
    public static function setOnHand($productId, $qty)
    {
        global $db;
        legacy_db();
        $db->exec("UPDATE stock_items SET on_hand = " . (int) $qty . " WHERE product_id = '" . $productId . "'");
        audit_log('stock', $productId, 'inventura', array('on_hand' => (int) $qty));
    }

    /**
     * Uvolni vsechny rezervace objednavky (zbozi je zase k dispozici).
     * Vraci pocet uvolnenych kusu.
     */
    public static function releaseReservations($orderId)
    {
        global $db;
        legacy_db();
        $released = 0;
        foreach ($db->query("SELECT product_id, reservations FROM stock_items") as $r) {
            $data = json_decode((string) $r['reservations'], true);
            if (!is_array($data) || !isset($data[$orderId])) {
                continue;
            }
            $released += (int) $data[$orderId];
            unset($data[$orderId]);
            // prazdne pole by se zakodovalo jako [], novy e-shop cte objekt
            $json = count($data) ? json_encode($data) : '{}';
            $db->exec("UPDATE stock_items SET reservations = " . $db->quote($json) . " WHERE product_id = " . $db->quote($r['product_id']));
        }

        return $released;
    }

    /**
     * Rezervace, jejichz objednavka uz je stornovana (visi tam).
     */
    public static function orphanReservations()
    {
        global $db;
        legacy_db();
        $out = array();
        foreach ($db->query("SELECT product_id, reservations FROM stock_items") as $r) {
            $data = json_decode((string) $r['reservations'], true);
            if (!is_array($data)) {
                continue;
            }
            foreach ($data as $orderId => $qty) {
                $o = $db->one("SELECT status FROM orders WHERE id = '" . $orderId . "'");
                if ($o === null || $o['status'] == 'cancelled') {
                    $out[] = array('product_id' => $r['product_id'], 'order_id' => $orderId, 'qty' => (int) $qty);
                }
            }
        }

        return $out;
    }
}
