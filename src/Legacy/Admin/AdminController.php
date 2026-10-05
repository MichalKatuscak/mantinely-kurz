<?php
/**
 * Nastenka administrace.
 */

namespace App\Legacy\Admin;

use App\Legacy\lib\OrderReport;
use App\Legacy\lib\StockReport;

class AdminController extends BaseController
{
    protected $title = 'Nástěnka';

    public function dashboardAction()
    {
        global $db;
        legacy_db();

        $stats = cache_get('dashboard_stats', 120);
        if ($stats === null) {
            $stats = array();
            $stats['orders_total'] = count_orders();
            $stats['orders_confirmed'] = count_orders('confirmed');
            $stats['orders_paid'] = count_orders('paid');
            $stats['orders_shipped'] = count_orders('shipped');
            $stats['customers'] = (int) $db->value("SELECT COUNT(*) FROM customers");
            $stats['products'] = (int) $db->value("SELECT COUNT(*) FROM products WHERE active = 1");
            $stats['revenue_month'] = monthlyRevenue(date('Y-m'));
            $stats['revenue_today'] = number_format(dailyRevenue(date('Y-m-d')) / 100, 2, ',', ' ');
            cache_set('dashboard_stats', $stats);
        }

        $lastOrders = $db->query("SELECT o.id, o.customer_id, o.status, o.placed_at, o.currency,"
            . " (SELECT SUM(quantity * unit_price_amount_in_cents) FROM order_items WHERE order_id = o.id) AS total"
            . " FROM orders o ORDER BY o.placed_at DESC LIMIT 10");
        foreach ($lastOrders as $k => $o) {
            $lastOrders[$k]['customer'] = customer_name($o['customer_id']);
        }

        $lowStock = StockReport::lowStock();

        $report = new OrderReport(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
        $report->load();

        $notes = $db->query("SELECT n.*, o.status FROM order_notes n LEFT JOIN orders o ON o.id = n.order_id ORDER BY n.created_at DESC LIMIT 5");

        return $this->renderLayout('dashboard', array(
            'stats'      => $stats,
            'lastOrders' => $lastOrders,
            'lowStock'   => $lowStock,
            'perDay'     => $report->perDay(),
            'cancelled'  => $report->cancelledRatio(),
            'average'    => $report->averageOrder(),
            'notes'      => $notes,
        ));
    }

    /**
     * Rychle hledani v hlavicce (objednavka / zakaznik / produkt).
     */
    public function searchAction()
    {
        global $db;
        legacy_db();
        $q = get_param('q');
        $results = array();
        if ($q !== '') {
            $like = "'%" . db_escape_old($q) . "%'";
            foreach ($db->query("SELECT id, status FROM orders WHERE id LIKE " . $like . " LIMIT 20") as $r) {
                $results[] = array('type' => 'Objednávka', 'label' => $r['id'] . ' (' . order_state_label($r['status']) . ')', 'url' => admin_url('order', array('id' => $r['id'])));
            }
            foreach ($db->query("SELECT id, name, email FROM customers WHERE name LIKE " . $like . " OR email LIKE " . $like . " LIMIT 20") as $r) {
                $results[] = array('type' => 'Zákazník', 'label' => $r['name'] . ' <' . $r['email'] . '>', 'url' => admin_url('customer', array('id' => $r['id'])));
            }
            foreach ($db->query("SELECT id, name, sku FROM products WHERE name LIKE " . $like . " OR sku LIKE " . $like . " LIMIT 20") as $r) {
                $results[] = array('type' => 'Produkt', 'label' => $r['name'] . ' (' . $r['sku'] . ')', 'url' => admin_url('product_edit', array('id' => $r['id'])));
            }
        }

        $rows = array();
        foreach ($results as $r) {
            $rows[] = array('Typ' => $r['type'], 'Výsledek' => '<a href="' . h($r['url']) . '">' . h($r['label']) . '</a>');
        }

        return $this->renderLayout('partials/table', array(
            'heading' => 'Hledání: ' . $q,
            'columns' => array('Typ', 'Výsledek'),
            'rows'    => $rows,
            'raw'     => array('Výsledek'),
        ), 'Hledání');
    }
}
