<?php
/**
 * CSV export pro ucetni (Pohoda import) a dalsi exporty.
 *
 * Oddelovac strednik, kodovani UTF-8 s BOM (Excel jinak neumi cestinu).
 * Ucetni (pani Novakova) chce v exportu i odeslane a dorucene objednavky
 * a castku PO sleve – proto si to tady pocitame sami a ne pres revenue.php.
 */

function csv_line($fields, $sep = ';')
{
    $out = array();
    foreach ($fields as $f) {
        $f = (string) $f;
        if (strpos($f, $sep) !== false || strpos($f, '"') !== false || strpos($f, "\n") !== false) {
            $f = '"' . str_replace('"', '""', $f) . '"';
        }
        $out[] = $f;
    }

    return implode($sep, $out) . "\r\n";
}

function csv_bom()
{
    return "\xEF\xBB\xBF";
}

/**
 * Castka pro CSV – ucetni chce desetinnou carku a BEZ mezer.
 */
function csv_amount($cents)
{
    return number_format(((int) $cents) / 100, 2, ',', '');
}

/**
 * Objednavky pro ucetni export v rozsahu datumu (YYYY-MM-DD).
 */
function exportOrders($from, $to)
{
    // pozor: $to bez casu => objednavky z posledniho dne po pulnoci tam nejsou
    // (ucetni o tom vi, exportuje vzdy do prvniho dne dalsiho mesice) -- jana 2017
    $sql = "SELECT o.id, o.customer_id, o.status, o.placed_at, o.currency, o.discount_amount_in_cents,"
        . " (SELECT SUM(i.quantity * i.unit_price_amount_in_cents) FROM order_items i WHERE i.order_id = o.id) AS items_total,"
        . " c.name AS customer_name, c.email AS customer_email"
        . " FROM orders o"
        . " LEFT JOIN customers c ON c.id = o.customer_id"
        . " WHERE o.status IN ('paid', 'shipped', 'delivered')"
        . " AND o.placed_at >= '" . $from . "' AND o.placed_at <= '" . $to . "'"
        . " ORDER BY o.placed_at";

    return db_query($sql);
}

/**
 * Soucet trzeb pro ucetni export.
 * Po sleve, BEZ prepoctu men (kazda mena se scita "jak je" – ucetni si to
 * prepocita v Pohode... snad).
 */
function exportRevenueSum($from, $to)
{
    $total = 0;
    foreach (exportOrders($from, $to) as $o) {
        $orderTotal = (int) $o['items_total'] - (int) $o['discount_amount_in_cents'];
        if ($orderTotal < 0) {
            $orderTotal = 0;
        }
        $total += $orderTotal;
    }

    return $total;
}

/**
 * Cely CSV soubor jako string.
 */
function exportCsv($from, $to)
{
    $out = csv_bom();
    $out .= csv_line(array('Číslo objednávky', 'Datum', 'Zákazník', 'E-mail', 'Stav', 'Měna', 'Položky', 'Sleva', 'Celkem'));
    foreach (exportOrders($from, $to) as $o) {
        $items = (int) $o['items_total'];
        $disc = (int) $o['discount_amount_in_cents'];
        $out .= csv_line(array(
            $o['id'],
            format_date($o['placed_at']),
            $o['customer_name'] !== null ? $o['customer_name'] : '',
            $o['customer_email'] !== null ? $o['customer_email'] : '',
            order_state_label($o['status']),
            $o['currency'],
            csv_amount($items),
            csv_amount($disc),
            csv_amount(max(0, $items - $disc)),
        ));
    }
    $out .= csv_line(array('', '', '', '', '', '', '', 'CELKEM', csv_amount(exportRevenueSum($from, $to))));

    return $out;
}

/**
 * Obecny export pole radku do CSV (pouziva customer_export.php).
 */
function rows_to_csv($rows, $header = null)
{
    $out = csv_bom();
    if ($header === null && count($rows) > 0) {
        $header = array_keys($rows[0]);
    }
    if ($header !== null) {
        $out .= csv_line($header);
    }
    foreach ($rows as $r) {
        $out .= csv_line(array_values($r));
    }

    return $out;
}

// TODO: import CSV produktu od dodavatele (rozdelano, viz suppliers.php)
function csv_parse($content, $sep = ';')
{
    $lines = preg_split('/\r\n|\n|\r/', (string) $content);
    $out = array();
    foreach ($lines as $l) {
        if (trim($l) === '') {
            continue;
        }
        $out[] = str_getcsv($l, $sep, '"', '\\');
    }

    return $out;
}
