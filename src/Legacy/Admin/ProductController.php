<?php
/**
 * Produkty – seznam. Editace je v product_edit.php.
 *
 * Tabulka products je porad "nase" – novy e-shop ji jen cte (katalog).
 */

namespace App\Legacy\Admin;

use App\Legacy\lib\PriceUtils;
use App\Legacy\lib\StockReport;

class ProductController extends BaseController
{
    protected $title = 'Produkty';

    public function listAction()
    {
        global $db;
        legacy_db();

        $q = get_param('q');
        $active = get_param('active', '1');
        $supplier = get_param('supplier');

        $sql = "SELECT p.*, s.on_hand, s.reservations, sp.name AS supplier_name"
            . " FROM products p"
            . " LEFT JOIN stock_items s ON s.product_id = p.id"
            . " LEFT JOIN suppliers sp ON sp.id = p.supplier_id"
            . " WHERE 1=1";
        if ($active !== '') {
            $sql .= " AND p.active = " . (int) $active;
        }
        if ($q != '') {
            $sql .= " AND (p.name LIKE '%" . $q . "%' OR p.sku LIKE '%" . $q . "%')";
        }
        if ($supplier != '') {
            $sql .= " AND p.supplier_id = " . (int) $supplier;
        }
        $sql .= " ORDER BY p.name";

        $products = $db->query($sql);
        foreach ($products as $k => $p) {
            $products[$k]['price_fmt'] = PriceUtils::format((int) $p['price_cents'], $p['currency']);
            $products[$k]['reserved'] = StockReport::reservedQty($p['reservations']);
        }

        $suppliers = array('' => '– všichni –');
        foreach ($db->query("SELECT id, name FROM suppliers ORDER BY name") as $s) {
            $suppliers[$s['id']] = $s['name'];
        }

        return $this->renderLayout('products/list', array(
            'products'  => $products,
            'q'         => $q,
            'active'    => $active,
            'supplier'  => $supplier,
            'suppliers' => $suppliers,
        ));
    }

    /**
     * Hromadna zmena cen o procenta (akce, zdrazeni...).
     */
    public function bulkPriceAction()
    {
        global $db;
        legacy_db();
        auth_require('obchod');
        csrf_check();
        $ids = post_param('ids', array());
        $percent = (float) str_replace(',', '.', (string) post_param('percent', '0'));
        if (!is_array($ids) || count($ids) == 0 || $percent == 0) {
            flash('Nic nevybráno', 'error');

            return $this->redirect(admin_url('products'));
        }
        // zaokrouhleni na cele koruny – obchod to tak chce
        $db->exec("UPDATE products SET price_cents = CAST(ROUND(price_cents * " . (1 + $percent / 100) . " / 100) * 100 AS INTEGER) WHERE id IN (" . ids_to_sql($ids) . ")");
        audit_log('product', implode(',', $ids), 'bulk_price', array('percent' => $percent));
        flash('Ceny upraveny o ' . $percent . ' %');

        return $this->redirect(admin_url('products'));
    }

    public function toggleAction()
    {
        global $db;
        legacy_db();
        auth_require('obchod');
        $id = get_param('id');
        $db->exec("UPDATE products SET active = 1 - active WHERE id = '" . $id . "'");
        audit_log('product', $id, 'toggle');

        return $this->redirect(admin_url('products'));
    }
}
