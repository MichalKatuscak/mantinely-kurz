<?php
/**
 * PUVODNI nastenka (2014). Nahrazeno AdminController::dashboardAction (2017).
 * Neni v menu ani v LegacyFrontController. Nemazat – Petr si nebyl jisty,
 * jestli to nekdo nema v zalozkach.
 */

global $db;
legacy_db();

if (!function_exists('dashboard_old_box')) {
    function dashboard_old_box($nadpis, $hodnota)
    {
        return '<div style="float:left;width:180px;border:1px solid #ccc;margin:5px;padding:10px"><small>' . h($nadpis) . '</small><br><big>' . h($hodnota) . '</big></div>';
    }
}

$dnes = date('Y-m-d');
$objednavekDnes = (int) $db->value("SELECT COUNT(*) FROM orders WHERE date(placed_at) = '" . $dnes . "'");
$nezaplaceno = count_orders('confirmed');
$kOdeslani = count_orders('paid');

// trzby dnes – jen CZK, stara logika
$trzbyDnes = 0;
foreach ($db->query("SELECT id FROM orders WHERE date(placed_at) = '" . $dnes . "' AND currency = 'CZK' AND status != 'cancelled'") as $o) {
    $trzbyDnes += order_total($o['id']);
}

$pageTitle = 'Nástěnka';
include LEGACY_TEMPLATES . '/partials/old_header.php';

echo '<h1>Vítejte v administraci</h1>';
echo dashboard_old_box('Objednávek dnes', $objednavekDnes);
echo dashboard_old_box('Tržby dnes', cena(hal2kc($trzbyDnes)));
echo dashboard_old_box('Nezaplaceno', $nezaplaceno);
echo dashboard_old_box('K odeslání', $kOdeslani);
echo '<div style="clear:both"></div>';

// echo '<iframe src="http://www.toplist.cz/stat/123456" width="400" height="300"></iframe>';

include LEGACY_TEMPLATES . '/partials/old_footer.php';
