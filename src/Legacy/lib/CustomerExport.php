<?php
/**
 * Export zakazniku (newsletter, marketing).
 * GDPR: od 2018 se exportuji jen zakaznici s newsletter = 1 (TODO overit s pravnikem)
 */

namespace App\Legacy\lib;

class CustomerExport
{
    public $onlyNewsletter = true;

    public $columns = array('id', 'email', 'name', 'phone', 'created_at');

    public function rows()
    {
        global $db;
        legacy_db();
        $sql = "SELECT " . implode(', ', $this->columns) . " FROM customers";
        if ($this->onlyNewsletter) {
            $sql .= " WHERE newsletter = 1";
        }
        $sql .= " ORDER BY created_at";

        return $db->query($sql);
    }

    /**
     * Zakaznici s utratou – utrata v CZK pres PriceUtils (vlastni kurzy).
     */
    public function rowsWithSpent()
    {
        global $db;
        legacy_db();
        $rows = $this->rows();
        foreach ($rows as $k => $r) {
            $orders = $db->query("SELECT o.currency, (SELECT SUM(quantity * unit_price_amount_in_cents) FROM order_items WHERE order_id = o.id) AS total"
                . " FROM orders o WHERE o.customer_id = '" . $r['id'] . "' AND o.status != 'cancelled'");
            $spent = 0;
            foreach ($orders as $o) {
                $spent += PriceUtils::toCzk((int) $o['total'], $o['currency']);
            }
            $rows[$k]['spent'] = PriceUtils::format($spent, 'CZK');
            $rows[$k]['orders'] = count($orders);
        }

        return $rows;
    }

    public function toCsv($withSpent = false)
    {
        $rows = $withSpent ? $this->rowsWithSpent() : $this->rows();

        return rows_to_csv($rows);
    }

    public function filename()
    {
        return 'zakaznici-' . date('Y-m-d') . '.csv';
    }
}
