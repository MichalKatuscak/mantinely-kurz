<?php
/**
 * Detail zakaznika.
 * Promenne: $customer, $orders, $spent
 */
?>
<h1><?php echo h($customer['name']); ?></h1>
<table class="grid" style="width:auto">
    <tr><th>E-mail</th><td><?php echo h($customer['email']); ?></td></tr>
    <tr><th>Telefon</th><td><?php echo h($customer['phone']); ?></td></tr>
    <tr><th>Registrace</th><td><?php echo format_date($customer['created_at'], true); ?></td></tr>
    <tr><th>Newsletter</th><td><?php echo yes_no($customer['newsletter']); ?></td></tr>
    <tr><th>Poznámka</th><td><?php echo nl2br(h($customer['note'])); ?></td></tr>
    <tr><th>Utraceno (CZK)</th><td><?php echo format_price($spent); ?></td></tr>
</table>
<p><a href="<?php echo h(admin_url('customer_edit', array('id' => $customer['id']))); ?>">Upravit</a></p>

<form method="post" action="<?php echo h(admin_url('customer_anonymize')); ?>" onsubmit="return confirm('Anonymizovat? Nelze vrátit.')" style="display:inline">
    <input type="hidden" name="id" value="<?php echo h($customer['id']); ?>"><input type="submit" value="Anonymizovat (GDPR)">
</form>
<form method="post" action="<?php echo h(admin_url('customer_delete')); ?>" onsubmit="return confirm('Smazat?')" style="display:inline">
    <input type="hidden" name="id" value="<?php echo h($customer['id']); ?>"><input type="submit" value="Smazat">
</form>

<h2>Objednávky</h2>
<table class="grid">
    <tr><th>Číslo</th><th>Datum</th><th>Stav</th><th class="num">Celkem</th></tr>
<?php foreach ($orders as $o) { ?>
    <tr class="state-<?php echo h($o['status']); ?>">
        <td><a href="<?php echo h(admin_url('order', array('id' => $o['id']))); ?>"><?php echo h($o['id']); ?></a></td>
        <td><?php echo format_date($o['placed_at']); ?></td>
        <td><?php echo h(order_state_label($o['status'])); ?></td>
        <td class="num"><?php echo format_price((int) $o['total'], $o['currency']); ?></td>
    </tr>
<?php } ?>
</table>
