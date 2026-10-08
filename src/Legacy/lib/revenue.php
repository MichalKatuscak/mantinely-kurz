<?php
/**
 * Vypocet trzeb.
 *
 * Pouziva: report mesicnich trzeb (lib/report.php), dashboard, stats.php.
 * Ucetni export (lib/csv.php) si to pocita SAM – driv to bylo spolecne,
 * ale ucetni chtela jinak (2017).
 *
 * @author petr 2015
 */

/**
 * SQL pro polozky zaplacenych objednavek v danem mesici.
 * $month ve formatu YYYY-MM
 */
function revenueSql($month)
{
    // MySQL verze: DATE_FORMAT(o.placed_at, '%Y-%m') = '...'
    $sql = "SELECT i.unit_price_amount_in_cents AS unitPrice, i.quantity AS quantity, i.unit_price_currency AS currency"
        . " FROM order_items i"
        . " JOIN orders o ON o.id = i.order_id"
        . " WHERE o.status = 'paid'"
        . " AND strftime('%Y-%m', o.placed_at) = '" . $month . "'";

    return $sql;
}

/**
 * Trzby za mesic v CZK, naformatovane ("1 200,00").
 */
function monthlyRevenue($month)
{
    // Vypocet presunut do tridy App\Legacy\Report\MonthlyRevenue (2026, modul 10).
    return (new \App\Legacy\Report\MonthlyRevenue(legacy_db()))->forMonth((string) $month);
}

/**
 * Prevod halere/centu na CZK halere podle kurzu v tabulce exchange_rates.
 */
function toCzk($cents, $currency)
{
    static $rates = null;
    static $ratesDb = null;
    // kurzy se nacitaji jednou za request (a znovu, kdyz se zmeni spojeni)
    if ($rates === null || $ratesDb !== legacy_db()) {
        $rates = array();
        foreach (db_query("SELECT currency, rate_to_czk FROM exchange_rates") as $row) {
            $rates[$row['currency']] = (float) $row['rate_to_czk'];
        }
        $ratesDb = legacy_db();
    }

    if (!isset($rates[$currency])) {
        // FIXME: neznama mena -> bereme jako CZK, ale to je asi spatne
        return $cents;
    }

    return $cents * $rates[$currency];
}

/**
 * Trzby za den – pro dashboard. Stejna logika jako monthlyRevenue, ale vraci cislo.
 * (zkopirovano, kdyz jsme potrebovali graf)
 */
function dailyRevenue($day)
{
    $rows = db_query("SELECT i.unit_price_amount_in_cents AS unitPrice, i.quantity AS quantity, i.unit_price_currency AS currency"
        . " FROM order_items i JOIN orders o ON o.id = i.order_id"
        . " WHERE o.status = 'paid' AND date(o.placed_at) = '" . $day . "'");
    $sum = 0;
    foreach ($rows as $r) {
        $line = $r['unitPrice'] * $r['quantity'];
        if ($r['currency'] !== 'CZK') {
            $line = toCzk($line, $r['currency']);
        }
        $sum += $line;
    }

    return $sum;
}

/**
 * Trzby za rok po mesicich – pole 'YYYY-MM' => float korun. Pouziva chart.php.
 */
function yearlyRevenue($year)
{
    $out = array();
    for ($m = 1; $m <= 12; $m++) {
        $month = $year . '-' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
        // monthlyRevenue vraci string "1 200,00", musime zpatky na cislo
        $s = monthlyRevenue($month);
        $out[$month] = (float) str_replace(array(' ', ','), array('', '.'), $s);
    }

    return $out;
}

/*
 * Stara verze s DPH, uz se nepouziva (od 2016 jsou ceny s DPH v DB)
 *
function monthlyRevenueVat($month)
{
    $sum = monthlyRevenue($month);
    return $sum * (1 + VAT_RATE / 100);
}
*/
