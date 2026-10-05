<?php
/**
 * Mesicni prehled – STARA verze reportu (2015), pred ReportController.
 * Vedeni si na to zvyklo, tak to zustalo v menu. Pocita si to SAMO
 * (bez prepoctu men, jen CZK objednavky, vsechny stavy krome storna).
 *
 * ?mesic=2015-06
 */

global $db;
legacy_db();
auth_require('ucetni');

$mesic = get_param('mesic', date('Y-m'));

$objednavky = $db->query("SELECT o.id, o.status, o.currency, o.discount_amount_in_cents FROM orders o"
    . " WHERE strftime('%Y-%m', o.placed_at) = '" . $mesic . "' AND o.status != 'cancelled'");

$trzby = 0;
$slevy = 0;
$pocet = 0;
$jineMeny = 0;
foreach ($objednavky as $o) {
    if ($o['currency'] != 'CZK') {
        $jineMeny++;
        continue;
    }
    $trzby += order_total($o['id']);
    $slevy += (int) $o['discount_amount_in_cents'];
    $pocet++;
}

// porovnani s minulym mesicem
$minuly = date('Y-m', strtotime($mesic . '-01 -1 month'));
$trzbyMinule = 0;
foreach ($db->query("SELECT id FROM orders WHERE currency = 'CZK' AND status != 'cancelled' AND strftime('%Y-%m', placed_at) = '" . $minuly . "'") as $o) {
    $trzbyMinule += order_total($o['id']);
}
$rozdil = $trzbyMinule > 0 ? round(($trzby - $trzbyMinule) / $trzbyMinule * 100, 1) : 0;

$pageTitle = 'Měsíční přehled';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Měsíční přehled <?php echo h(report_month_label($mesic)); ?></h1>
<form method="get">
    <select name="mesic"><?php echo select_options(report_month_options(), $mesic); ?></select>
    <input type="submit" value="Zobrazit">
</form>
<table class="grid" style="width:auto">
    <tr><th>Objednávek (CZK)</th><td class="num"><?php echo $pocet; ?></td></tr>
    <tr><th>Tržby (před slevou)</th><td class="num"><?php echo cena(hal2kc($trzby)); ?></td></tr>
    <tr><th>Slevy</th><td class="num"><?php echo cena(hal2kc($slevy)); ?></td></tr>
    <tr><th>Tržby po slevě</th><td class="num"><strong><?php echo cena(hal2kc($trzby - $slevy)); ?></strong></td></tr>
    <tr><th>Minulý měsíc</th><td class="num"><?php echo cena(hal2kc($trzbyMinule)); ?> (<?php echo $rozdil > 0 ? '+' : ''; ?><?php echo $rozdil; ?> %)</td></tr>
</table>
<?php if ($jineMeny > 0) { ?>
<p class="hint">Objednávek v cizí měně (nezapočteno): <?php echo $jineMeny; ?></p>
<?php } ?>
<p class="hint">Pro účetní čísla použijte <a href="<?php echo h(admin_url('report', array('month' => $mesic))); ?>">Report tržeb</a>.</p>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
