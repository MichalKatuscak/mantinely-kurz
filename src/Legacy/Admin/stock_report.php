<?php
/**
 * Report skladu pro skladniky – hodnota skladu, obratovost.
 * (StockController je novejsi, tohle je "tiskovy" report z 2016.)
 */

use App\Legacy\lib\PriceUtils;

global $db;
legacy_db();
auth_require('sklad');

$radky = $db->query("SELECT p.id, p.sku, p.name, p.price_cents, p.currency, p.active, s.on_hand, s.reservations"
    . " FROM products p LEFT JOIN stock_items s ON s.product_id = p.id ORDER BY p.sku");

// prodano za poslednich 90 dni (vsechny stavy krome storna a konceptu)
$prodano = array();
$od90 = date('Y-m-d H:i:s', strtotime('-90 days'));
foreach ($db->query("SELECT i.product_id, SUM(i.quantity) AS q FROM order_items i JOIN orders o ON o.id = i.order_id"
    . " WHERE o.status NOT IN ('cancelled','draft') AND o.placed_at >= '" . $od90 . "' GROUP BY i.product_id") as $r) {
    $prodano[$r['product_id']] = (int) $r['q'];
}

$hodnotaCelkem = 0;
$pageTitle = 'Report skladu';
include LEGACY_TEMPLATES . '/partials/old_header.php';

echo '<h1>Report skladu</h1>';
echo '<table class="grid">';
echo '<tr><th>SKU</th><th>Produkt</th><th class="num">Skladem</th><th class="num">Rezerv.</th><th class="num">Prodáno 90 dní</th><th class="num">Vydrží (dní)</th><th class="num">Hodnota</th></tr>';
foreach ($radky as $r) {
    if ($r['on_hand'] === null) {
        continue; // bez skladove karty
    }
    $rez = 0;
    $json = json_decode((string) $r['reservations'], true);
    if (is_array($json)) {
        foreach ($json as $q) {
            $rez += (int) $q;
        }
    }
    $p90 = isset($prodano[$r['id']]) ? $prodano[$r['id']] : 0;
    $vydrzi = $p90 > 0 ? (int) floor(((int) $r['on_hand'] - $rez) / ($p90 / 90)) : '∞';
    // hodnota skladu v CZK (kurz z PriceUtils, ne z exchange_rates)
    $hodnota = PriceUtils::toCzk((int) $r['on_hand'] * (int) $r['price_cents'], $r['currency']);
    $hodnotaCelkem += $hodnota;

    $low = ((int) $r['on_hand'] - $rez) < config('low_stock', 5);
    echo '<tr' . ($r['active'] ? '' : ' style="color:#999"') . '>';
    echo '<td>' . h($r['sku']) . '</td>';
    echo '<td>' . h($r['name']) . '</td>';
    echo '<td class="num">' . (int) $r['on_hand'] . '</td>';
    echo '<td class="num">' . $rez . '</td>';
    echo '<td class="num">' . $p90 . '</td>';
    echo '<td class="num' . ($low ? ' low' : '') . '">' . $vydrzi . '</td>';
    echo '<td class="num">' . PriceUtils::format($hodnota) . '</td>';
    echo '</tr>';
}
echo '<tr><td colspan="6"><strong>Hodnota skladu celkem (prodejní ceny)</strong></td><td class="num"><strong>' . PriceUtils::format($hodnotaCelkem) . '</strong></td></tr>';
echo '</table>';

include LEGACY_TEMPLATES . '/partials/old_footer.php';
