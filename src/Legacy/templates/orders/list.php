<?php
/**
 * Seznam objednavek (novejsi, z OrderController::listAction).
 * Promenne: $orders, $status, $q, $from, $to, $pager, $total, $states
 */
?>
<h1>Objednávky (<?php echo (int) $total; ?>)</h1>

<form method="get">
    Stav: <select name="status"><option value="">– vše –</option><?php echo select_options($states, $status); ?></select>
    Hledat: <input type="text" name="q" value="<?php echo h($q); ?>">
    Od: <input type="date" name="from" value="<?php echo h($from); ?>">
    Do: <input type="date" name="to" value="<?php echo h($to); ?>">
    <input type="submit" value="Filtrovat">
</form>

<form method="post" action="<?php echo h(admin_url('orders_bulk')); ?>" onsubmit="return confirm('Opravdu stornovat vybrané objednávky?')"><?php echo csrf_field(); ?>
<table class="grid">
    <tr>
        <th><input type="checkbox" onclick="var c=document.querySelectorAll('.chk');for(var i=0;i<c.length;i++)c[i].checked=this.checked"></th>
        <th>Číslo</th><th>Datum</th><th>Zákazník</th><th>Stav</th><th class="num">Položky</th><th class="num">Sleva</th><th class="num">Celkem</th><th>Pozn.</th><th></th>
    </tr>
<?php foreach ($orders as $o) { ?>
    <?php $oTotal = (int) $o['total']; $oDisc = (int) $o['discount_amount_in_cents']; ?>
    <tr class="state-<?php echo h($o['status']); ?>">
        <td><input type="checkbox" class="chk" name="ids[]" value="<?php echo h($o['id']); ?>"></td>
        <td><a href="<?php echo h(admin_url('order', array('id' => $o['id']))); ?>"><?php echo h(substr($o['id'], 0, 8)); ?>…</a></td>
        <td><?php echo format_date($o['placed_at'], true); ?></td>
        <td><?php echo h($o['customer_name'] !== null ? $o['customer_name'] : '(není v customers)'); ?><br><small><?php echo h($o['customer_email']); ?></small></td>
        <td><?php echo h(order_state_label($o['status'])); ?></td>
        <td class="num"><?php echo format_price($oTotal, $o['currency']); ?></td>
        <td class="num"><?php echo $oDisc > 0 ? '−' . format_price($oDisc, $o['discount_currency']) : ''; ?></td>
        <td class="num"><strong><?php echo format_price(max(0, $oTotal - $oDisc), $o['currency']); ?></strong></td>
        <td><?php echo (int) $o['notes'] > 0 ? (int) $o['notes'] : ''; ?></td>
        <td><a href="<?php echo h(admin_url('order_edit', array('id' => $o['id']))); ?>">upravit</a></td>
    </tr>
<?php } ?>
</table>
<p><input type="hidden" name="action" value="storno"><input type="submit" value="Stornovat vybrané"></p>
</form>
<?php echo $pager; ?>
