<?php
/**
 * Pomocne funkce pro reporty (mesicni trzby, top produkty...).
 * Samotne vypocty jsou v revenue.php.
 */

/**
 * Hlavicka reportu – nazev a datum vygenerovani.
 */
function report_header($title = 'Report měsíčních tržeb')
{
    return array(
        'title'     => $title,
        'generated' => 'vygenerováno ' . date('j. n. Y'),
        'shop'      => config('shop_name', ''),
    );
}

/**
 * Radky mesicniho reportu: popisek => hodnota.
 */
function report_monthly_rows($month)
{
    $rows = array();
    $rows[] = array('label' => 'Měsíc', 'value' => report_month_label($month));
    $rows[] = array('label' => 'Počet zaplacených objednávek', 'value' => report_paid_count($month));
    $rows[] = array('label' => 'Tržby celkem (CZK)', 'value' => monthlyRevenue($month));

    return $rows;
}

/**
 * 2026-09 -> "září 2026"
 */
function report_month_label($month)
{
    $names = array(
        1 => 'leden', 2 => 'únor', 3 => 'březen', 4 => 'duben', 5 => 'květen', 6 => 'červen',
        7 => 'červenec', 8 => 'srpen', 9 => 'září', 10 => 'říjen', 11 => 'listopad', 12 => 'prosinec',
    );
    $parts = explode('-', (string) $month);
    if (count($parts) != 2) {
        return (string) $month;
    }
    $m = (int) $parts[1];

    return (isset($names[$m]) ? $names[$m] : $parts[1]) . ' ' . $parts[0];
}

function report_paid_count($month)
{
    $r = db_one("SELECT COUNT(*) AS c FROM orders o WHERE o.status = 'paid' AND strftime('%Y-%m', o.placed_at) = '" . $month . "'");

    return $r ? (int) $r['c'] : 0;
}

/**
 * Top produkty za mesic podle poctu kusu. Pouziva top_products.php.
 * (Tady se zase pocitaji i odeslane a dorucene, viz FIXME.)
 */
function report_top_products($month, $limit = 10)
{
    // FIXME: monthlyRevenue bere jen 'paid', tady jsou i shipped/delivered – sjednotit?
    $sql = "SELECT i.product_id, p.name, SUM(i.quantity) AS qty, SUM(i.quantity * i.unit_price_amount_in_cents) AS total"
        . " FROM order_items i"
        . " JOIN orders o ON o.id = i.order_id"
        . " LEFT JOIN products p ON p.id = i.product_id"
        . " WHERE o.status IN ('paid','shipped','delivered')"
        . " AND strftime('%Y-%m', o.placed_at) = '" . $month . "'"
        . " GROUP BY i.product_id, p.name ORDER BY qty DESC LIMIT " . (int) $limit;

    return db_query($sql);
}

/**
 * Pocty objednavek podle stavu za mesic.
 */
function report_states($month)
{
    $sql = "SELECT status, COUNT(*) AS c FROM orders WHERE strftime('%Y-%m', placed_at) = '" . $month . "' GROUP BY status";
    $out = array();
    foreach (db_query($sql) as $r) {
        $out[$r['status']] = (int) $r['c'];
    }

    return $out;
}

/**
 * Seznam poslednich 12 mesicu pro select.
 */
function report_month_options()
{
    $out = array();
    $ts = strtotime(date('Y-m-01'));
    for ($i = 0; $i < 12; $i++) {
        $m = date('Y-m', strtotime('-' . $i . ' month', $ts));
        $out[$m] = report_month_label($m);
    }

    return $out;
}
