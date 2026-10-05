<?php
/**
 * Seznam faktur.
 * Promenne: $invoices, $year, $sums
 */
?>
<h1>Faktury <?php echo h($year); ?></h1>
<form method="get">Rok: <input type="text" name="year" value="<?php echo h($year); ?>" size="4"> <input type="submit" value="Zobrazit"></form>
<table class="grid">
    <tr><th>Číslo</th><th>Vystaveno</th><th>Objednávka</th><th>Zákazník</th><th class="num">Částka</th><th></th></tr>
<?php foreach ($invoices as $inv) { ?>
    <tr>
        <td><?php echo h($inv['number']); ?></td>
        <td><?php echo format_date($inv['issued_at']); ?></td>
        <td><a href="<?php echo h(admin_url('order', array('id' => $inv['order_id']))); ?>"><?php echo h(substr($inv['order_id'], 0, 8)); ?>…</a></td>
        <td><?php echo h($inv['customer']); ?></td>
        <td class="num"><?php echo h($inv['total_fmt']); ?></td>
        <td><a href="<?php echo h(admin_url('invoice_print', array('id' => $inv['id']))); ?>">tisk</a></td>
    </tr>
<?php } ?>
</table>
<h2>Součty podle měny</h2>
<ul>
<?php foreach ($sums as $cur => $s) { ?>
    <li><?php echo h($cur); ?>: <?php echo format_price($s, $cur); ?></li>
<?php } ?>
</ul>
