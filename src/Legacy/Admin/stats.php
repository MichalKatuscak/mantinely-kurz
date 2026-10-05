<?php
/**
 * Statistiky prodeju za obdobi.
 * ?od=2017-01-01&do=2017-01-31
 *
 * Pozor: "Obrat" se tu pocita pres OrderReport (vsechny stavy krome storna
 * a konceptu, kurzy z PriceUtils), tedy JINAK nez report trzeb.
 */

use App\Legacy\lib\OrderReport;
use App\Legacy\lib\PriceUtils;

global $db;
legacy_db();
auth_require();

$od = get_param('od', date('Y-m-01'));
$do = get_param('do', date('Y-m-d'));

$report = new OrderReport($od, $do);
$report->load();

$poctyStavu = $report->countByState();

// po menach
$poMenach = $db->query("SELECT o.currency, COUNT(DISTINCT o.id) AS pocet, SUM(i.quantity * i.unit_price_amount_in_cents) AS suma"
    . " FROM orders o JOIN order_items i ON i.order_id = o.id"
    . " WHERE o.placed_at >= '" . $od . " 00:00:00' AND o.placed_at <= '" . $do . " 23:59:59' AND o.status != 'cancelled'"
    . " GROUP BY o.currency");

// noví zakaznici v obdobi
$noviZakaznici = (int) $db->value("SELECT COUNT(*) FROM customers WHERE created_at >= '" . $od . "' AND created_at <= '" . $do . " 23:59:59'");

// slevy celkem
$slevy = (int) $db->value("SELECT SUM(discount_amount_in_cents) FROM orders WHERE placed_at >= '" . $od . "' AND placed_at <= '" . $do . " 23:59:59' AND status != 'cancelled'");

// prumerny pocet polozek
$prumerPolozek = $db->value("SELECT AVG(c) FROM (SELECT COUNT(*) AS c FROM order_items i JOIN orders o ON o.id = i.order_id"
    . " WHERE o.placed_at >= '" . $od . "' AND o.placed_at <= '" . $do . " 23:59:59' GROUP BY o.id)");

$pageTitle = 'Statistiky';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Statistiky <?php echo h(datum($od)); ?> – <?php echo h(datum($do)); ?></h1>
<form method="get">
    Od: <input type="date" name="od" value="<?php echo h($od); ?>">
    Do: <input type="date" name="do" value="<?php echo h($do); ?>">
    <input type="submit" value="Zobrazit">
</form>

<table class="grid" style="width:auto">
    <tr><th>Objednávek</th><td class="num"><?php echo count($report->rows); ?></td></tr>
    <tr><th>Obrat (CZK, bez storen)</th><td class="num"><?php echo PriceUtils::format($report->total()); ?></td></tr>
    <tr><th>Průměrná objednávka</th><td class="num"><?php echo PriceUtils::format($report->averageOrder()); ?></td></tr>
    <tr><th>Slevy celkem</th><td class="num"><?php echo format_price($slevy); ?></td></tr>
    <tr><th>Průměrně položek</th><td class="num"><?php echo $prumerPolozek !== null ? number_format((float) $prumerPolozek, 1, ',', ' ') : '–'; ?></td></tr>
    <tr><th>Nových zákazníků</th><td class="num"><?php echo $noviZakaznici; ?></td></tr>
    <tr><th>Storno poměr</th><td class="num"><?php echo h($report->cancelledRatio()); ?> %</td></tr>
</table>

<h2>Podle stavu</h2>
<table class="grid" style="width:auto">
<?php foreach ($poctyStavu as $stav => $pocet) { ?>
    <tr><td><?php echo h(order_state_label($stav)); ?></td><td class="num"><?php echo (int) $pocet; ?></td></tr>
<?php } ?>
</table>

<h2>Podle měny</h2>
<table class="grid" style="width:auto">
    <tr><th>Měna</th><th>Objednávek</th><th>Součet</th></tr>
<?php foreach ($poMenach as $m) { ?>
    <tr><td><?php echo h($m['currency']); ?></td><td class="num"><?php echo (int) $m['pocet']; ?></td><td class="num"><?php echo format_price((int) $m['suma'], $m['currency']); ?></td></tr>
<?php } ?>
</table>

<p><a href="<?php echo h(admin_url('chart', array('rok' => substr($od, 0, 4)))); ?>">Graf tržeb za rok »</a></p>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
