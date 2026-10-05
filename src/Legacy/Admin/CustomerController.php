<?php
/**
 * Zakaznici.
 * Od 2024 zakladaji zakazniky i v novem e-shopu (customer_id v orders),
 * do tabulky customers se ale dostanou jen pres registraci ve starem formulari
 * nebo rucne tady. FIXME: synchronizace
 */

namespace App\Legacy\Admin;

class CustomerController extends BaseController
{
    protected $title = 'Zákazníci';

    public function listAction()
    {
        global $db;
        legacy_db();

        $q = get_param('q');
        $newsletter = get_param('newsletter');
        list($offset, $limit, $page) = paginate(get_param('page', 1));

        $where = '';
        if ($q != '') {
            $where = " WHERE (c.name LIKE '%" . $q . "%' OR c.email LIKE '%" . $q . "%' OR c.phone LIKE '%" . $q . "%')";
        }
        if ($newsletter !== '') {
            $where .= ($where == '' ? ' WHERE ' : ' AND ') . "c.newsletter = " . (int) $newsletter;
        }

        $total = (int) $db->value("SELECT COUNT(*) FROM customers c" . $where);
        $customers = $db->query("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS orders_count"
            . " FROM customers c" . $where . " ORDER BY c.created_at DESC LIMIT " . $limit . " OFFSET " . $offset);

        return $this->renderLayout('customers/list', array(
            'customers'  => $customers,
            'q'          => $q,
            'newsletter' => $newsletter,
            'total'      => $total,
            'pager'      => pager_html($page, $total, $limit, admin_url('customers', array('q' => $q))),
        ));
    }

    public function detailAction()
    {
        global $db;
        legacy_db();

        $id = get_param('id');
        $customer = $db->one("SELECT * FROM customers WHERE id = '" . $id . "'");
        if ($customer === null) {
            return $this->notFound('Zákazník neexistuje');
        }
        $orders = $db->query("SELECT o.*, (SELECT SUM(quantity * unit_price_amount_in_cents) FROM order_items WHERE order_id = o.id) AS total"
            . " FROM orders o WHERE o.customer_id = '" . $id . "' ORDER BY o.placed_at DESC");

        // utrata – jen CZK objednavky, ostatni meny se nepocitaji (FIXME)
        $spent = 0;
        foreach ($orders as $o) {
            if ($o['currency'] == 'CZK' && $o['status'] != 'cancelled') {
                $spent += (int) $o['total'] - (int) $o['discount_amount_in_cents'];
            }
        }

        return $this->renderLayout('customers/detail', array(
            'customer' => $customer,
            'orders'   => $orders,
            'spent'    => $spent,
        ), 'Zákazník ' . $customer['email']);
    }

    /**
     * Smazani zakaznika – jen kdyz nema objednavky.
     */
    public function deleteAction()
    {
        global $db;
        legacy_db();
        $id = post_param('id');
        if ($id == '') {
            return $this->redirect(admin_url('customers'));
        }
        $n = (int) $db->value("SELECT COUNT(*) FROM orders WHERE customer_id = '" . $id . "'");
        if ($n > 0) {
            flash('Zákazník má objednávky, nelze smazat (GDPR: použijte anonymizaci).', 'error');

            return $this->redirect(admin_url('customer', array('id' => $id)));
        }
        $db->exec("DELETE FROM customers WHERE id = '" . $id . "'");
        audit_log('customer', $id, 'delete');
        flash('Zákazník smazán');

        return $this->redirect(admin_url('customers'));
    }

    /**
     * Anonymizace (GDPR 2018).
     */
    public function anonymizeAction()
    {
        global $db;
        legacy_db();
        $id = post_param('id');
        $db->exec("UPDATE customers SET email = 'anonym-" . substr(md5($id), 0, 8) . "@example.invalid', name = 'Anonymizováno', phone = '', note = '', newsletter = 0 WHERE id = '" . $id . "'");
        audit_log('customer', $id, 'anonymize');
        flash('Zákazník anonymizován');

        return $this->redirect(admin_url('customer', array('id' => $id)));
    }
}
