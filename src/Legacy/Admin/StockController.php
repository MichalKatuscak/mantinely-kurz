<?php
/**
 * Sklad. Data jsou v stock_items (spravuje novy e-shop), rezervace v JSONu.
 */

namespace App\Legacy\Admin;

use App\Legacy\lib\StockReport;

class StockController extends BaseController
{
    protected $title = 'Sklad';

    public function listAction()
    {
        global $db;
        legacy_db();

        $onlyLow = get_param('low') == '1';
        $rows = $onlyLow ? StockReport::lowStock() : StockReport::all();

        // produkty bez zaznamu ve skladu
        $missing = $db->query("SELECT p.id, p.name, p.sku FROM products p LEFT JOIN stock_items s ON s.product_id = p.id WHERE s.product_id IS NULL AND p.active = 1");

        $totalOnHand = 0;
        $totalReserved = 0;
        foreach ($rows as $r) {
            $totalOnHand += (int) $r['on_hand'];
            $totalReserved += (int) $r['reserved'];
        }

        return $this->renderLayout('stock/list', array(
            'rows'          => $rows,
            'missing'       => $missing,
            'onlyLow'       => $onlyLow,
            'totalOnHand'   => $totalOnHand,
            'totalReserved' => $totalReserved,
            'orphans'       => StockReport::orphanReservations(),
        ));
    }

    /**
     * Detail rezervaci produktu.
     */
    public function reservationsAction()
    {
        global $db;
        legacy_db();
        $pid = get_param('product');
        $res = StockReport::reservations($pid);
        $rows = array();
        foreach ($res as $orderId => $qty) {
            $o = $db->one("SELECT status, placed_at FROM orders WHERE id = '" . $orderId . "'");
            $rows[] = array(
                'Objednávka' => $orderId,
                'Kusů'       => (int) $qty,
                'Stav'       => $o ? order_state_label($o['status']) : '(neexistuje)',
                'Datum'      => $o ? format_date($o['placed_at']) : '',
            );
        }

        return $this->renderLayout('partials/table', array(
            'heading' => 'Rezervace produktu ' . product_name($pid),
            'columns' => array('Objednávka', 'Kusů', 'Stav', 'Datum'),
            'rows'    => $rows,
        ), 'Rezervace');
    }

    /**
     * Inventura – rucni prepis stavu.
     */
    public function inventoryAction()
    {
        if (is_post()) {
            $qty = post_param('qty', array());
            if (is_array($qty)) {
                foreach ($qty as $pid => $q) {
                    if ($q === '') {
                        continue;
                    }
                    StockReport::setOnHand($pid, (int) $q);
                }
            }
            flash('Inventura uložena');

            return $this->redirect(admin_url('stock'));
        }

        return $this->renderLayout('stock/list', array(
            'rows'          => StockReport::all(),
            'missing'       => array(),
            'onlyLow'       => false,
            'totalOnHand'   => 0,
            'totalReserved' => 0,
            'orphans'       => array(),
            'inventory'     => true,
        ), 'Inventura');
    }
}
