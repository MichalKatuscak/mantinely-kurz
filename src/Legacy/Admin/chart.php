<?php
/**
 * Graf trzeb po mesicich (SVG, driv Google Charts – zrusili API).
 * ?rok=2017
 */

global $db;
legacy_db();
auth_require();

$rok = (int) get_param('rok', date('Y'));
if ($rok < 2014 || $rok > 2100) {
    $rok = (int) date('Y');
}

$data = cache_get('chart_' . $rok, 3600);
if ($data === null) {
    $data = yearlyRevenue($rok);
    cache_set('chart_' . $rok, $data);
}

// pocty objednavek po mesicich (vsechny stavy)
$pocty = array();
foreach ($db->query("SELECT strftime('%Y-%m', placed_at) AS m, COUNT(*) AS c FROM orders WHERE strftime('%Y', placed_at) = '" . $rok . "' GROUP BY m") as $r) {
    $pocty[$r['m']] = (int) $r['c'];
}

$max = 0;
foreach ($data as $v) {
    if ($v > $max) {
        $max = $v;
    }
}
if ($max == 0) {
    $max = 1;
}

$sirka = 600;
$vyska = 250;
$sloupec = (int) floor($sirka / 12);

$pageTitle = 'Graf tržeb';
include LEGACY_TEMPLATES . '/partials/old_header.php';

echo '<h1>Tržby ' . $rok . ' (CZK, zaplacené)</h1>';
echo '<p><a href="' . h(admin_url('chart', array('rok' => $rok - 1))) . '">« ' . ($rok - 1) . '</a> | <a href="' . h(admin_url('chart', array('rok' => $rok + 1))) . '">' . ($rok + 1) . ' »</a></p>';
echo '<svg width="' . ($sirka + 40) . '" height="' . ($vyska + 40) . '" style="border:1px solid #ccc;background:#fff">';
$i = 0;
foreach ($data as $mesic => $hodnota) {
    $h = (int) round($hodnota / $max * $vyska);
    $x = 20 + $i * $sloupec;
    $y = 10 + $vyska - $h;
    echo '<rect x="' . $x . '" y="' . $y . '" width="' . ($sloupec - 6) . '" height="' . $h . '" fill="#2b4a6b"><title>' . h($mesic) . ': ' . number_format($hodnota, 2, ',', ' ') . ' Kč</title></rect>';
    echo '<text x="' . ($x + 4) . '" y="' . ($vyska + 28) . '" font-size="10">' . substr($mesic, 5, 2) . '</text>';
    $i++;
}
echo '</svg>';

echo '<table class="grid" style="width:auto;margin-top:10px"><tr><th>Měsíc</th><th>Tržby</th><th>Objednávek</th></tr>';
foreach ($data as $mesic => $hodnota) {
    echo '<tr><td>' . h(report_month_label($mesic)) . '</td><td class="num">' . number_format($hodnota, 2, ',', ' ') . ' Kč</td><td class="num">' . (isset($pocty[$mesic]) ? $pocty[$mesic] : 0) . '</td></tr>';
}
echo '</table>';

include LEGACY_TEMPLATES . '/partials/old_footer.php';
