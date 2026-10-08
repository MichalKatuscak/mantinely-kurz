<?php
/**
 * Faktury.
 */

namespace App\Legacy\Admin;

use App\Legacy\lib\InvoiceHelper;
use App\Legacy\lib\PriceUtils;

class InvoiceController extends BaseController
{
    protected $title = 'Faktury';

    public function listAction()
    {
        global $db;
        legacy_db();

        $year = get_param('year', date('Y'));
        $invoices = $db->query("SELECT i.*, o.customer_id, o.status FROM invoices i LEFT JOIN orders o ON o.id = i.order_id"
            . " WHERE strftime('%Y', i.issued_at) = '" . $year . "' ORDER BY i.number DESC");

        $sums = array();
        foreach ($invoices as $k => $inv) {
            $invoices[$k]['customer'] = $inv['customer_id'] !== null ? customer_name($inv['customer_id']) : '';
            $invoices[$k]['total_fmt'] = PriceUtils::format((int) $inv['total_cents'], $inv['currency']);
            if (!isset($sums[$inv['currency']])) {
                $sums[$inv['currency']] = 0;
            }
            $sums[$inv['currency']] += (int) $inv['total_cents'];
        }

        return $this->renderLayout('invoices/list', array(
            'invoices' => $invoices,
            'year'     => $year,
            'sums'     => $sums,
        ));
    }

    public function issueAction()
    {
        auth_require('ucetni');
        $orderId = get_param('order');
        $id = InvoiceHelper::issue($orderId);
        if ($id === false) {
            flash('Fakturu nelze vystavit (objednávka není zaplacená?)', 'error');

            return $this->redirect(admin_url('order', array('id' => $orderId)));
        }
        flash('Faktura vystavena');

        return $this->redirect(admin_url('invoice_print', array('id' => $id)));
    }

    public function detailAction()
    {
        $invoice = InvoiceHelper::load(get_param('id'));
        if ($invoice === null) {
            return $this->notFound('Faktura neexistuje');
        }
        $items = InvoiceHelper::items($invoice['order_id']);

        return $this->renderLayout('invoices/detail', array(
            'invoice' => $invoice,
            'items'   => $items,
            'vat'     => InvoiceHelper::vatSummary($invoice),
        ), 'Faktura ' . $invoice['number']);
    }
}
