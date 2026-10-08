<?php
/**
 * Objednavky – seznam a detail.
 *
 * Editace stavu a slevy je v order_edit.php, hromadne storno v orders.php
 * (stare stranky, nikdo je neprepsal).
 */

namespace App\Legacy\Admin;

use App\Ordering\Application\Command\CancelOrder;
use App\Ordering\Domain\Exception\InvalidOrderStateTransitionException;
use App\Ordering\Domain\ValueObject\OrderId;

class OrderController extends BaseController
{
    protected $title = 'Objednávky';

    public function listAction()
    {
        global $db;
        legacy_db();

        $status = get_param('status');
        $q = get_param('q');
        $from = get_param('from');
        $to = get_param('to');
        list($offset, $limit, $page) = paginate(get_param('page', 1));

        $where = " WHERE 1=1";
        if ($status != '') {
            $where .= " AND o.status = '" . $status . "'";
        }
        if ($q != '') {
            $where .= " AND (o.id LIKE '%" . $q . "%' OR c.email LIKE '%" . $q . "%' OR c.name LIKE '%" . $q . "%')";
        }
        if ($from != '') {
            $where .= " AND o.placed_at >= '" . $from . "'";
        }
        if ($to != '') {
            $where .= " AND o.placed_at <= '" . $to . " 23:59:59'";
        }

        $total = (int) $db->value("SELECT COUNT(*) FROM orders o LEFT JOIN customers c ON c.id = o.customer_id" . $where);

        $orders = $db->query("SELECT o.*, c.name AS customer_name, c.email AS customer_email,"
            . " (SELECT SUM(quantity * unit_price_amount_in_cents) FROM order_items WHERE order_id = o.id) AS total,"
            . " (SELECT COUNT(*) FROM order_notes WHERE order_id = o.id) AS notes"
            . " FROM orders o LEFT JOIN customers c ON c.id = o.customer_id"
            . $where
            . " ORDER BY o.placed_at DESC"
            . " LIMIT " . (int) $limit . " OFFSET " . (int) $offset);

        return $this->renderLayout('orders/list', array(
            'orders' => $orders,
            'status' => $status,
            'q'      => $q,
            'from'   => $from,
            'to'     => $to,
            'pager'  => pager_html($page, $total, $limit, admin_url('orders', array('status' => $status, 'q' => $q))),
            'total'  => $total,
            'states' => $GLOBALS['ORDER_STATES'],
        ));
    }

    public function detailAction()
    {
        global $db;
        legacy_db();

        $id = get_param('id');
        $order = $db->one("SELECT * FROM orders WHERE id = '" . $id . "'");
        if ($order === null) {
            return $this->notFound('Objednávka ' . $id . ' neexistuje');
        }

        $items = $db->query("SELECT i.*, p.name, p.sku FROM order_items i LEFT JOIN products p ON p.id = i.product_id WHERE i.order_id = '" . $id . "' ORDER BY i.id");
        $customer = $db->one("SELECT * FROM customers WHERE id = '" . $order['customer_id'] . "'");
        $notes = $db->query("SELECT * FROM order_notes WHERE order_id = '" . $id . "' ORDER BY created_at DESC");
        $invoice = $db->one("SELECT * FROM invoices WHERE order_id = '" . $id . "'");
        $history = $db->query("SELECT * FROM audit_log WHERE entity = 'order' AND entity_id = '" . $id . "' ORDER BY created_at DESC LIMIT 50");

        $sum = 0;
        foreach ($items as $it) {
            $sum += $it['quantity'] * $it['unit_price_amount_in_cents'];
        }

        return $this->renderLayout('orders/detail', array(
            'order'    => $order,
            'items'    => $items,
            'customer' => $customer,
            'notes'    => $notes,
            'invoice'  => $invoice,
            'history'  => $history,
            'sum'      => $sum,
            'toPay'    => max(0, $sum - (int) $order['discount_amount_in_cents']),
        ), 'Objednávka ' . $id);
    }

    /**
     * Storno jedne objednavky z detailu (2026).
     *
     * Stornuje novy e-shop (CancelOrder): ten hlida, co jde stornovat,
     * spocita vratku a uvolni rezervace ve skladu. Tady se jen zapise
     * historie, poznamka k vratce a posle mail zakaznikovi.
     */
    public function cancelAction()
    {
        global $db;
        legacy_db();
        auth_require('obchod');
        if (is_post()) {
            csrf_check();
        }

        $id = get_param('id');
        $order = $db->one("SELECT * FROM orders WHERE id = " . $db->quote($id));
        if ($order === null) {
            return $this->notFound('Objednávka ' . $id . ' neexistuje');
        }
        if (!is_post()) {
            return $this->redirect(admin_url('order', array('id' => $id)));
        }
        if ($order['status'] == 'cancelled') {
            flash('Objednávka už je stornovaná', 'error');

            return $this->detailAction();
        }

        $reason = trim((string) $this->post('reason'));
        if ($reason == '') {
            $reason = 'Storno v administraci';
        }

        try {
            $refund = legacy_command(new CancelOrder(OrderId::fromString($id), $reason));
        } catch (InvalidOrderStateTransitionException $e) {
            flash('Objednávku ve stavu „' . order_state_label($order['status']) . '“ nelze stornovat', 'error');

            return $this->detailAction();
        }

        $refundCents = $refund->amountInCents;
        $refundCurrency = $refund->currency->value;
        audit_log('order', $id, 'storno', array(
            'from'   => $order['status'],
            'reason' => $reason,
            'refund' => $refundCents,
        ));

        $msg = 'Objednávka stornována, zboží vráceno na sklad.';
        if ($refundCents > 0) {
            $db->exec("INSERT INTO order_notes (order_id, author, note, created_at) VALUES (" . $db->quote($id) . ", " . $db->quote(auth_login_name()) . ", "
                . $db->quote('Storno: vrátit zákazníkovi ' . format_price($refundCents, $refundCurrency)) . ", '" . date('Y-m-d H:i:s') . "')");
            $msg .= ' Zákazníkovi se vrací ' . format_price($refundCents, $refundCurrency) . '.';
        }

        $c = $db->one("SELECT email FROM customers WHERE id = " . $db->quote($order['customer_id']));
        if ($c) {
            $body = "Dobrý den,\n\nvaše objednávka " . $id . " byla stornována.\n";
            if ($refundCents > 0) {
                $body .= "Zaplacenou částku " . format_price($refundCents, $refundCurrency) . " vám vrátíme na účet.\n";
            }
            send_mail($c['email'], 'Vaše objednávka byla stornována', $body);
        }

        cache_delete('dashboard_stats');
        flash($msg);

        return $this->detailAction();
    }

    /**
     * Objednavky zakaznika (volano z detailu zakaznika pres AJAX, 2016).
     */
    public function byCustomerAction()
    {
        global $db;
        legacy_db();
        $cid = get_param('customer');
        $orders = $db->query("SELECT o.id, o.status, o.placed_at, o.currency FROM orders o WHERE o.customer_id = '" . $cid . "' ORDER BY o.placed_at DESC");
        $html = '<ul>';
        foreach ($orders as $o) {
            $html .= '<li><a href="' . h(admin_url('order', array('id' => $o['id']))) . '">' . h($o['id']) . '</a> – '
                . h(order_state_label($o['status'])) . ', ' . format_date($o['placed_at']) . ', '
                . format_price(order_total($o['id']), $o['currency']) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * Zmena mnozstvi polozky z detailu objednavky (2026).
     *
     * Mnozstvi meni novy e-shop (ChangeItemQuantity): ten hlida, ze je objednavka
     * rozpracovana a mnozstvi kladne. Tady se jen ukaze vysledek.
     */
    public function changeItemQuantityAction()
    {
        global $db;
        legacy_db();
        auth_require('obchod');
        if (is_post()) {
            csrf_check();
        }

        $id = get_param('id');
        $order = $db->one("SELECT * FROM orders WHERE id = " . $db->quote($id));
        if ($order === null) {
            return $this->notFound('Objednávka ' . $id . ' neexistuje');
        }
        if (!is_post()) {
            return $this->redirect(admin_url('order', array('id' => $id)));
        }

        try {
            legacy_command(new \App\Ordering\Application\Command\ChangeItemQuantity(
                \App\Ordering\Domain\ValueObject\OrderId::fromString($id),
                \App\Ordering\Domain\ValueObject\ProductId::fromString((string) $this->post('product')),
                (int) $this->post('quantity')
            ));
        } catch (\App\Ordering\Domain\Exception\InvalidOrderStateTransitionException $e) {
            flash('Množství jde změnit jen u rozpracované objednávky', 'error');

            return $this->detailAction();
        } catch (\App\Ordering\Domain\Exception\InvalidQuantityException $e) {
            flash('Množství musí být kladné celé číslo', 'error');

            return $this->detailAction();
        }

        flash('Množství položky změněno');

        return $this->detailAction();
    }
}
