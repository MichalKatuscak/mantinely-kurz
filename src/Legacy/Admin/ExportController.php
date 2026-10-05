<?php
/**
 * Exporty z administrace. Ucetni export dela totez co /legacy/export.php.
 */

namespace App\Legacy\Admin;

use App\Legacy\lib\CustomerExport;

class ExportController extends BaseController
{
    protected $title = 'Exporty';

    /**
     * Formular s vyberem obdobi.
     */
    public function indexAction()
    {
        $from = get_param('from', date('Y-m-01', strtotime('first day of last month')));
        $to = get_param('to', date('Y-m-t', strtotime('last day of last month')));

        $html = '<h1>Účetní export</h1>'
            . '<form method="get" action="' . h(admin_url('export_csv')) . '">'
            . 'Od: <input type="date" name="from" value="' . h($from) . '"> '
            . 'Do: <input type="date" name="to" value="' . h($to) . '"> '
            . '<input type="submit" value="Stáhnout CSV"></form>'
            . '<p>Součet tržeb za období (pro kontrolu): <strong>' . csv_amount(exportRevenueSum($from, $to)) . '</strong></p>'
            . '<p class="hint">Export obsahuje zaplacené, odeslané a doručené objednávky, částky po slevě.</p>'
            . '<h2>Zákazníci</h2><p><a href="' . h(admin_url('customer_export')) . '">Export zákazníků s newsletterem</a></p>';

        return $this->renderLayout('partials/raw', array('html' => $html));
    }

    /**
     * Vraci CSV jako string. Content-Type nastavi LegacyFrontController.
     */
    public function ordersAction()
    {
        $from = get_param('from', date('Y-m-01'));
        $to = get_param('to', date('Y-m-t'));
        $GLOBALS['LEGACY_CONTENT_TYPE'] = 'text/csv; charset=utf-8';
        $GLOBALS['LEGACY_FILENAME'] = 'export-' . $from . '_' . $to . '.csv';

        return exportCsv($from, $to);
    }

    public function customersAction()
    {
        $exp = new CustomerExport();
        $exp->onlyNewsletter = get_param('all') != '1';
        $GLOBALS['LEGACY_CONTENT_TYPE'] = 'text/csv; charset=utf-8';
        $GLOBALS['LEGACY_FILENAME'] = $exp->filename();

        return $exp->toCsv(get_param('spent') == '1');
    }
}
