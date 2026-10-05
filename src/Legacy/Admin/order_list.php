<?php
/**
 * Seznam objednavek – PUVODNI verze (2014).
 * Nahrazeno OrderController::listAction, ale skladnici chteji tuhle,
 * protoze je na jedne strance vsechno a jde vytisknout.
 */

global $db;
legacy_db();
auth_require();

$stav = isset($_GET['stav']) ? (string) $_GET['stav'] : '';
$od = isset($_GET['od']) ? (string) $_GET['od'] : date('Y-m-d', strtotime('-7 days'));
$razeni = isset($_GET['razeni']) ? (string) $_GET['razeni'] : 'placed_at DESC';

$sql = "SELECT * FROM orders WHERE placed_at >= '" . $od . "'";
if ($stav != '') {
    $sql .= " AND status = '" . $stav . "'";
}
// razeni primo z URL (!) – FIXME
$sql .= " ORDER BY " . $razeni;

$objednavky = $db->query($sql);

$pageTitle = 'Objednávky';
include LEGACY_TEMPLATES . '/partials/old_header.php';

echo '<h1>Objednávky od ' . h(datum($od)) . '</h1>';
echo '<form method="get">';
echo 'Od: <input type="text" name="od" value="' . h($od) . '" size="10"> ';
echo 'Stav: <select name="stav"><option value="">vše</option>';
foreach ($GLOBALS['ORDER_STATES'] as $k => $v) {
    echo '<option value="' . $k . '"' . ($k == $stav ? ' selected' : '') . '>' . $v . '</option>';
}
echo '</select> ';
echo '<input type="submit" value="Zobrazit"></form>';

echo '<table class="grid">';
echo '<tr><th>#</th><th>Datum</th><th>Zákazník</th><th>Stav</th><th>Položek</th><th>Celkem</th></tr>';
$i = 0;
$celkem = 0;
foreach ($objednavky as $o) {
    $i++;
    $polozky = $db->query("SELECT * FROM order_items WHERE order_id = '" . $o['id'] . "'");
    $suma = 0;
    $kusu = 0;
    foreach ($polozky as $p) {
        $suma += $p['quantity'] * $p['unit_price_amount_in_cents'];
        $kusu += $p['quantity'];
    }
    // celkem jen CZK, ostatni meny se nescitaji (2014 jsme meli jen CZK)
    if ($o['currency'] == 'CZK' && $o['status'] != 'cancelled') {
        $celkem += $suma;
    }
    echo '<tr' . ($o['status'] == 'cancelled' ? ' style="color:#999"' : '') . '>';
    echo '<td>' . $i . '</td>';
    echo '<td>' . datum($o['placed_at']) . '</td>';
    echo '<td>' . h(customer_name($o['customer_id'])) . '</td>';
    echo '<td>' . order_state_label($o['status']) . '</td>';
    echo '<td class="num">' . $kusu . '</td>';
    echo '<td class="num">' . format_price($suma, $o['currency']) . '</td>';
    echo '</tr>';
}
echo '<tr><td colspan="5"><strong>Celkem (CZK, bez storen)</strong></td><td class="num"><strong>' . format_price($celkem) . '</strong></td></tr>';
echo '</table>';

if ($i == 0) {
    echo '<p>Žádné objednávky.</p>';
}

// stary export do Excelu – nefunguje od prechodu na UTF-8
// echo '<a href="order_list.php?xls=1">Export XLS</a>';

include LEGACY_TEMPLATES . '/partials/old_footer.php';
