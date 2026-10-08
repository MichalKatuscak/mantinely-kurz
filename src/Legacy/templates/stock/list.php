<?php
/**
 * Sklad / inventura.
 * Promenne: $rows, $missing, $onlyLow, $totalOnHand, $totalReserved, $orphans, (volitelne) $inventory
 */
$inventory = isset($inventory) && $inventory;
?>
<h1><?php echo $inventory ? 'Inventura' : 'Sklad'; ?></h1>
<?php if (!$inventory) { ?>
<p>
    <a href="<?php echo h(admin_url('stock', array('low' => $onlyLow ? '0' : '1'))); ?>"><?php echo $onlyLow ? 'Zobrazit vše' : 'Jen docházející'; ?></a> |
    <a href="<?php echo h(admin_url('stock_inventory')); ?>">Inventura</a>
</p>
<?php } ?>
<form method="post" action="<?php echo h(admin_url('stock_inventory')); ?>"><?php echo csrf_field(); ?>
<table class="grid">
    <tr><th>SKU</th><th>Produkt</th><th class="num">Skladem</th><th class="num">Rezervováno</th><th class="num">Dostupné</th><?php if ($inventory) { ?><th>Nový stav</th><?php } ?></tr>
<?php foreach ($rows as $r) { ?>
    <tr>
        <td><?php echo h($r['sku']); ?></td>
        <td><a href="<?php echo h(admin_url('stock_reservations', array('product' => $r['product_id']))); ?>"><?php echo h($r['name'] !== null ? $r['name'] : $r['product_id']); ?></a></td>
        <td class="num"><?php echo (int) $r['on_hand']; ?></td>
        <td class="num"><?php echo (int) $r['reserved']; ?></td>
        <td class="num<?php echo $r['low'] ? ' low' : ''; ?>"><?php echo (int) $r['available']; ?></td>
        <?php if ($inventory) { ?><td><input type="text" size="5" name="qty[<?php echo h($r['product_id']); ?>]"></td><?php } ?>
    </tr>
<?php } ?>
<?php if (!$inventory) { ?>
    <tr><td colspan="2"><strong>Celkem</strong></td><td class="num"><?php echo (int) $totalOnHand; ?></td><td class="num"><?php echo (int) $totalReserved; ?></td><td></td></tr>
<?php } ?>
</table>
<?php if ($inventory) { ?><p><input type="submit" value="Uložit inventuru"></p><?php } ?>
</form>

<?php if (count($missing) > 0) { ?>
<h2>Aktivní produkty bez skladové karty</h2>
<ul>
<?php foreach ($missing as $m) { ?>
    <li><?php echo h($m['name']); ?> (<?php echo h($m['sku']); ?>)</li>
<?php } ?>
</ul>
<?php } ?>

<?php if (count($orphans) > 0) { ?>
<h2 class="low">Rezervace stornovaných objednávek</h2>
<p class="hint">Tyto rezervace blokují zboží, i když objednávka už neplatí.</p>
<table class="grid">
<?php foreach ($orphans as $o) { ?>
    <tr><td><?php echo h(product_name($o['product_id'])); ?></td><td><?php echo h($o['order_id']); ?></td><td class="num"><?php echo (int) $o['qty']; ?> ks</td></tr>
<?php } ?>
</table>
<?php } ?>
