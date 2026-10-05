<?php
/**
 * Reporty pro vedeni.
 *
 * Mesicni report trzeb: ?month=2017-03
 * Vypocet je v lib/revenue.php (monthlyRevenue), sestaveni radku v lib/report.php.
 */

namespace App\Legacy\Admin;

class ReportController extends BaseController
{
    protected $title = 'Reporty';

    public function monthlyAction()
    {
        $month = isset($_GET['month']) ? (string) $_GET['month'] : '';
        if ($month == '') {
            $month = date('Y-m');
        }
        // TODO: validace formatu (is_month), ted se to posila rovnou do SQL
        // if (!is_month($month)) { $month = date('Y-m'); }

        $header = report_header();
        $rows = report_monthly_rows($month);

        return $this->render('report/monthly', array(
            'header' => $header,
            'rows'   => $rows,
            'month'  => $month,
        ));
    }

    /**
     * Top produkty – stary report, ted je to v top_products.php.
     * @deprecated
     */
    public function topAction()
    {
        $month = isset($_GET['month']) ? (string) $_GET['month'] : date('Y-m');
        $rows = report_top_products($month, 20);
        $html = '<h1>Top produkty ' . h(report_month_label($month)) . '</h1><table class="grid">';
        foreach ($rows as $r) {
            $html .= '<tr><td>' . h($r['name'] !== null ? $r['name'] : $r['product_id']) . '</td><td>' . (int) $r['qty'] . '</td></tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Rocni prehled po mesicich.
     */
    public function yearAction()
    {
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        $data = yearlyRevenue($year);
        $html = '<h1>Tržby ' . $year . '</h1><table class="grid"><tr><th>Měsíc</th><th>Tržby (CZK)</th></tr>';
        $total = 0;
        foreach ($data as $m => $v) {
            $html .= '<tr><td>' . h(report_month_label($m)) . '</td><td class="num">' . number_format($v, 2, ',', ' ') . '</td></tr>';
            $total += $v;
        }
        $html .= '<tr class="sum"><td>Celkem</td><td class="num">' . number_format($total, 2, ',', ' ') . '</td></tr></table>';

        return $this->renderLayout('partials/raw', array('html' => $html), 'Roční přehled');
    }
}
