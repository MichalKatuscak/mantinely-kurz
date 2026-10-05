<?php
/**
 * Prodeje podle meny a mesice – pro ucetni pri uzaverce.
 * Ukazuje castky v puvodni mene i prepocet (exchange_rates).
 * ?rok=2017
 */

global $db;
legacy_db();
auth_require('ucetni');

$rok = (int) get_param('rok', date('Y'));

$data = $db->query("SELECT strftime('%Y-%m', o.placed_at) AS mesic, o.currency, o.status,"
    . " SUM(i.quantity * i.unit_price_amount_in_cents) AS suma"
    . " FROM orders o JOIN order_items i ON i.order_id = o.id"
    . " WHERE strftime('%Y', o.placed_at) = '" . $rok . "'"
    . " GROUP BY mesic, o.currency, o.status ORDER BY mesic");

// mesic => mena => array(zaplaceno, vse)
$tabulka = array();
foreach ($data as $r) {
    $m = $r['mesic'];
    $c = $r['currency'];
    if (!isset($tabulka[$m])) {
        $tabulka[$m] = array();
    }
    if (!isset($tabulka[$m][$c])) {
        $tabulka[$m][$c] = array('paid' => 0, 'all' => 0);
    }
    if ($r['status'] == 'cancelled') {
        continue;
    }
    $tabulka[$m][$c]['all'] += (int) $r['suma'];
    if ($r['status'] == 'paid') {
        $tabulka[$m][$c]['paid'] += (int) $r['suma'];
    }
}

$pageTitle = 'Prodeje podle měny';
include LEGACY_TEMPLATES . '/partials/old_header.php';

echo '<h1>Prodeje podle měny ' . $rok . '</h1>';
echo '<table class="grid"><tr><th>Měsíc</th><th>Měna</th><th class="num">Zaplaceno</th><th class="num">Vše bez storen</th><th class="num">Zaplaceno v CZK</th></tr>';
foreach ($tabulka as $mesic => $meny) {
    foreach ($meny as $mena => $s) {
        $czk = $mena == 'CZK' ? $s['paid'] : toCzk($s['paid'], $mena);
        echo '<tr>';
        echo '<td>' . h(report_month_label($mesic)) . '</td>';
        echo '<td>' . h($mena) . '</td>';
        echo '<td class="num">' . format_price($s['paid'], $mena) . '</td>';
        echo '<td class="num">' . format_price($s['all'], $mena) . '</td>';
        echo '<td class="num">' . format_price((int) round($czk)) . '</td>';
        echo '</tr>';
    }
}
echo '</table>';
echo '<p class="hint">„Zaplaceno“ = stav Zaplacená. Odeslané a doručené objednávky jsou jen ve sloupci „Vše“.</p>';

include LEGACY_TEMPLATES . '/partials/old_footer.php';
