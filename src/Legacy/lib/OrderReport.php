<?php
/**
 * Prehled objednavek pro stats.php a dashboard.
 * (jana.k 2017 – "objektove", ale data jsou porad v poli)
 */

namespace App\Legacy\lib;

class OrderReport
{
    public $from;

    public $to;

    public $rows = array();

    public function __construct($from = null, $to = null)
    {
        $this->from = $from !== null ? $from : date('Y-m-01');
        $this->to = $to !== null ? $to : date('Y-m-d');
    }

    public function load()
    {
        global $db;
        legacy_db();
        $sql = "SELECT o.*, (SELECT SUM(quantity * unit_price_amount_in_cents) FROM order_items WHERE order_id = o.id) AS total"
            . " FROM orders o WHERE o.placed_at >= '" . $this->from . " 00:00:00'"
            . " AND o.placed_at <= '" . $this->to . " 23:59:59'"
            . " ORDER BY o.placed_at DESC";
        $this->rows = $db->query($sql);

        return $this;
    }

    public function countByState()
    {
        $out = array();
        foreach ($this->rows as $r) {
            if (!isset($out[$r['status']])) {
                $out[$r['status']] = 0;
            }
            $out[$r['status']]++;
        }

        return $out;
    }

    /**
     * Soucet za obdobi – v CZK podle PriceUtils kurzu (ne exchange_rates!),
     * vcetne vsech stavu krome stornovanych.
     */
    public function total()
    {
        $sum = 0;
        foreach ($this->rows as $r) {
            if ($r['status'] == 'cancelled' || $r['status'] == 'draft') {
                continue;
            }
            $sum += PriceUtils::toCzk((int) $r['total'], $r['currency']);
        }

        return $sum;
    }

    public function averageOrder()
    {
        $n = 0;
        foreach ($this->rows as $r) {
            if ($r['status'] != 'cancelled' && $r['status'] != 'draft') {
                $n++;
            }
        }
        if ($n == 0) {
            return 0;
        }

        return (int) round($this->total() / $n);
    }

    /**
     * Po dnech pro graf: 'YYYY-MM-DD' => pocet
     */
    public function perDay()
    {
        $out = array();
        foreach ($this->rows as $r) {
            if ($r['placed_at'] === null) {
                continue;
            }
            $d = substr($r['placed_at'], 0, 10);
            if (!isset($out[$d])) {
                $out[$d] = 0;
            }
            $out[$d]++;
        }
        ksort($out);

        return $out;
    }

    public function cancelledRatio()
    {
        $c = $this->countByState();
        $all = count($this->rows);
        if ($all == 0) {
            return 0;
        }

        return round((isset($c['cancelled']) ? $c['cancelled'] : 0) / $all * 100, 1);
    }
}
