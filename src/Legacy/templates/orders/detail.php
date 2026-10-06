<?php
/**
 * Detail objednavky.
 * Promenne: $order, $items, $customer, $notes, $invoice, $history, $sum, $toPay
 */
?>
<h1>Objednávka <?php echo h($order['id']); ?></h1>

<table class="grid" style="width:auto">
    <tr><th>Stav</th><td class="state-<?php echo h($order['status']); ?>"><?php echo h(order_state_label($order['status'])); ?></td></tr>
    <tr><th>Datum objednání</th><td><?php echo format_date($order['placed_at'], true); ?></td></tr>
    <tr><th>Měna</th><td><?php echo h($order['currency']); ?></td></tr>
    <tr><th>Zákazník</th><td>
        <?php if ($customer) { ?>
            <a href="<?php echo h(admin_url('customer', array('id' => $customer['id']))); ?>"><?php echo h($customer['name']); ?></a>
            &lt;<?php echo h($customer['email']); ?>&gt; <?php echo h($customer['phone']); ?>
        <?php } else { ?>
            <?php echo h($order['customer_id']); ?> <span class="hint">(zákazník z nového e-shopu)</span>
        <?php } ?>
    </td></tr>
    <tr><th>Faktura</th><td>
        <?php if ($invoice) { ?>
            <a href="<?php echo h(admin_url('invoice_print', array('id' => $invoice['id']))); ?>"><?php echo h($invoice['number']); ?></a>
        <?php } elseif (in_array($order['status'], array('paid', 'shipped', 'delivered'))) { ?>
            <a href="<?php echo h(admin_url('invoice_issue', array('order' => $order['id']))); ?>">vystavit fakturu</a>
        <?php } else { ?>
            –
        <?php } ?>
    </td></tr>
</table>

<h2>Položky</h2>
<table class="grid">
    <tr><th>Produkt</th><th>SKU</th><th class="num">Ks</th><th class="num">Cena/ks</th><th class="num">Celkem</th></tr>
<?php foreach ($items as $it) { ?>
    <tr>
        <td><?php echo h($it['name'] !== null ? $it['name'] : $it['product_id']); ?></td>
        <td><?php echo h($it['sku']); ?></td>
        <td class="num"><?php echo (int) $it['quantity']; ?></td>
        <td class="num"><?php echo format_price($it['unit_price_amount_in_cents'], $it['unit_price_currency']); ?></td>
        <td class="num"><?php echo format_price($it['quantity'] * $it['unit_price_amount_in_cents'], $it['unit_price_currency']); ?></td>
    </tr>
<?php } ?>
    <tr><td colspan="4" class="num">Mezisoučet</td><td class="num"><?php echo format_price($sum, $order['currency']); ?></td></tr>
    <tr><td colspan="4" class="num">Sleva</td><td class="num">−<?php echo format_price($order['discount_amount_in_cents'], $order['discount_currency']); ?></td></tr>
    <tr><td colspan="4" class="num"><strong>K úhradě</strong></td><td class="num"><strong><?php echo format_price($toPay, $order['currency']); ?></strong></td></tr>
</table>

<p>
    <a href="<?php echo h(admin_url('order_edit', array('id' => $order['id']))); ?>">Změnit stav / slevu</a> |
    <a href="<?php echo h(admin_url('order_notes', array('order' => $order['id']))); ?>">Poznámky (<?php echo count($notes); ?>)</a>
</p>

<?php if (in_array($order['status'], array('draft', 'confirmed', 'paid'))) { ?>
<form method="post" action="<?php echo h(admin_url('order_cancel', array('id' => $order['id']))); ?>"
      onsubmit="return confirm('Opravdu stornovat objednávku? Zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad.');">
    Důvod storna: <input type="text" name="reason" size="40">
    <input type="submit" value="Stornovat objednávku">
</form>
<?php } ?>

<h2>Poznámky</h2>
<?php foreach ($notes as $n) { ?>
    <p><strong><?php echo h($n['author']); ?></strong>, <?php echo format_date($n['created_at'], true); ?>:<br><?php echo nl2br(h($n['note'])); ?></p>
<?php } ?>

<h2>Historie</h2>
<table class="grid">
<?php foreach ($history as $hrow) { ?>
    <tr><td><?php echo format_date($hrow['created_at'], true); ?></td><td><?php echo h($hrow['action']); ?></td><td><code><?php echo h($hrow['payload']); ?></code></td></tr>
<?php } ?>
</table>
