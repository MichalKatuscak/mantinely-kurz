<?php
/**
 * Nejprodavanejsi produkty za mesic.
 * ?month=2017-05&limit=20
 */

global $db;
legacy_db();
auth_require();

$month = get_param('month', date('Y-m'));
$limit = (int) get_param('limit', 20);
if ($limit <= 0 || $limit > 500) {
    $limit = 20;
}

$rows = report_top_products($month, $limit);

// podil na celku (kusy)
$celkemKusu = 0;
foreach ($rows as $r) {
    $celkemKusu += (int) $r['qty'];
}

// porovnani s minulym mesicem
$minuly = date('Y-m', strtotime($month . '-01 -1 month'));
$minule = array();
foreach (report_top_products($minuly, 500) as $r) {
    $minule[$r['product_id']] = (int) $r['qty'];
}

$pageTitle = 'Top produkty';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Top produkty – <?php echo h(report_month_label($month)); ?></h1>
<form method="get">
    <select name="month"><?php echo select_options(report_month_options(), $month); ?></select>
    Počet: <input type="text" name="limit" value="<?php echo (int) $limit; ?>" size="3">
    <input type="submit" value="Zobrazit">
</form>
<table class="grid">
    <tr><th>#</th><th>Produkt</th><th class="num">Kusů</th><th class="num">Podíl</th><th class="num">Minulý měsíc</th><th class="num">Tržba (měna položky)</th></tr>
<?php $poradi = 0; foreach ($rows as $r) { $poradi++; ?>
    <?php $min = isset($minule[$r['product_id']]) ? $minule[$r['product_id']] : 0; ?>
    <tr>
        <td><?php echo $poradi; ?></td>
        <td><?php echo h($r['name'] !== null ? $r['name'] : $r['product_id']); ?></td>
        <td class="num"><?php echo (int) $r['qty']; ?></td>
        <td class="num"><?php echo $celkemKusu > 0 ? round($r['qty'] / $celkemKusu * 100, 1) : 0; ?> %</td>
        <td class="num"><?php echo $min; ?><?php if ($min > 0) { echo (int) $r['qty'] >= $min ? ' ▲' : ' ▼'; } ?></td>
        <td class="num"><?php echo format_price((int) $r['total']); ?></td>
    </tr>
<?php } ?>
</table>
<p class="hint">Zahrnuje zaplacené, odeslané i doručené objednávky. Tržba je součet bez přepočtu měn (FIXME).</p>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
