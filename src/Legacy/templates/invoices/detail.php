<?php
/**
 * Detail faktury.
 * Promenne: $invoice, $items, $vat
 */
?>
<h1>Faktura <?php echo h($invoice['number']); ?></h1>
<p>Vystaveno: <?php echo format_date($invoice['issued_at']); ?>, objednávka
    <a href="<?php echo h(admin_url('order', array('id' => $invoice['order_id']))); ?>"><?php echo h($invoice['order_id']); ?></a></p>
<table class="grid">
    <tr><th>Položka</th><th class="num">Ks</th><th class="num">Cena/ks</th><th class="num">Celkem</th></tr>
<?php foreach ($items as $it) { ?>
    <tr>
        <td><?php echo h($it['name']); ?></td>
        <td class="num"><?php echo (int) $it['quantity']; ?></td>
        <td class="num"><?php echo \App\Legacy\lib\PriceUtils::format((int) $it['unit_price_amount_in_cents'], $it['unit_price_currency']); ?></td>
        <td class="num"><?php echo \App\Legacy\lib\PriceUtils::format($it['quantity'] * $it['unit_price_amount_in_cents'], $it['unit_price_currency']); ?></td>
    </tr>
<?php } ?>
</table>
<table class="grid" style="width:auto;margin-top:10px">
    <tr><th>Základ</th><td class="num"><?php echo \App\Legacy\lib\PriceUtils::format($vat['base'], $invoice['currency']); ?></td></tr>
    <tr><th>DPH <?php echo (int) $vat['rate']; ?> %</th><td class="num"><?php echo \App\Legacy\lib\PriceUtils::format($vat['vat'], $invoice['currency']); ?></td></tr>
    <tr><th>Celkem</th><td class="num"><strong><?php echo \App\Legacy\lib\PriceUtils::format($vat['total'], $invoice['currency']); ?></strong></td></tr>
</table>
<p><a href="<?php echo h(admin_url('invoice_print', array('id' => $invoice['id']))); ?>">Tisk</a></p>
