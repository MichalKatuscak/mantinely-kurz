<?php
/**
 * Nastenka.
 * Promenne: $stats, $lastOrders, $lowStock, $perDay, $cancelled, $average, $notes
 */
?>
<h1>Nástěnka</h1>

<table class="grid" style="width:auto">
    <tr><th>Objednávek celkem</th><td class="num"><?php echo (int) $stats['orders_total']; ?></td></tr>
    <tr><th>Čeká na zaplacení</th><td class="num"><?php echo (int) $stats['orders_confirmed']; ?></td></tr>
    <tr><th>Zaplaceno, k odeslání</th><td class="num"><?php echo (int) $stats['orders_paid']; ?></td></tr>
    <tr><th>Odesláno</th><td class="num"><?php echo (int) $stats['orders_shipped']; ?></td></tr>
    <tr><th>Zákazníků</th><td class="num"><?php echo (int) $stats['customers']; ?></td></tr>
    <tr><th>Aktivních produktů</th><td class="num"><?php echo (int) $stats['products']; ?></td></tr>
    <tr><th>Tržby tento měsíc</th><td class="num"><?php echo h($stats['revenue_month']); ?> Kč</td></tr>
    <tr><th>Tržby dnes</th><td class="num"><?php echo h($stats['revenue_today']); ?> Kč</td></tr>
    <tr><th>Průměrná objednávka (30 dní)</th><td class="num"><?php echo format_price($average); ?></td></tr>
    <tr><th>Stornovaných (30 dní)</th><td class="num"><?php echo h($cancelled); ?> %</td></tr>
</table>

<h2>Poslední objednávky</h2>
<table class="grid">
    <tr><th>Číslo</th><th>Zákazník</th><th>Stav</th><th>Datum</th><th class="num">Celkem</th></tr>
<?php foreach ($lastOrders as $o) { ?>
    <tr class="state-<?php echo h($o['status']); ?>">
        <td><a href="<?php echo h(admin_url('order', array('id' => $o['id']))); ?>"><?php echo h(substr($o['id'], 0, 8)); ?>…</a></td>
        <td><?php echo h($o['customer']); ?></td>
        <td><?php echo h(order_state_label($o['status'])); ?></td>
        <td><?php echo format_date($o['placed_at'], true); ?></td>
        <td class="num"><?php echo format_price((int) $o['total'], $o['currency']); ?></td>
    </tr>
<?php } ?>
</table>

<?php if (count($lowStock) > 0) { ?>
<h2 class="low">Dochází zboží</h2>
<ul>
<?php foreach ($lowStock as $s) { ?>
    <li><?php echo h($s['name'] !== null ? $s['name'] : $s['product_id']); ?> – dostupné <?php echo (int) $s['available']; ?> ks (skladem <?php echo (int) $s['on_hand']; ?>, rezervováno <?php echo (int) $s['reserved']; ?>)</li>
<?php } ?>
</ul>
<?php } ?>

<h2>Objednávky za 30 dní</h2>
<?php if (count($perDay) == 0) { ?>
    <p class="hint">Žádné objednávky.</p>
<?php } else { ?>
<div style="display:flex;align-items:flex-end;height:120px;gap:2px">
<?php $maxDay = max($perDay); foreach ($perDay as $d => $n) { ?>
    <div title="<?php echo h($d . ': ' . $n); ?>" style="background:#2b4a6b;width:12px;height:<?php echo (int) round($n / max(1, $maxDay) * 110); ?>px"></div>
<?php } ?>
</div>
<?php } ?>

<h2>Poslední poznámky</h2>
<ul>
<?php foreach ($notes as $n) { ?>
    <li><strong><?php echo h($n['author']); ?></strong> (<?php echo format_date($n['created_at'], true); ?>): <?php echo h(truncate($n['note'], 80)); ?></li>
<?php } ?>
</ul>
