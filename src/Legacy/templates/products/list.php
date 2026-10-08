<?php
/**
 * Seznam produktu.
 * Promenne: $products, $q, $active, $supplier, $suppliers
 */
?>
<h1>Produkty</h1>
<form method="get">
    <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="název / SKU">
    <select name="active"><?php echo select_options(array('' => '– vše –', '1' => 'aktivní', '0' => 'neaktivní'), $active); ?></select>
    <select name="supplier"><?php echo select_options($suppliers, $supplier); ?></select>
    <input type="submit" value="Filtrovat">
    <a href="<?php echo h(admin_url('product_edit')); ?>">+ nový produkt</a>
</form>

<form method="post" action="<?php echo h(admin_url('products_bulk_price')); ?>"><?php echo csrf_field(); ?>
<table class="grid">
    <tr><th></th><th>SKU</th><th>Název</th><th>Dodavatel</th><th class="num">Cena</th><th class="num">Skladem</th><th class="num">Rezervováno</th><th>Aktivní</th><th></th></tr>
<?php foreach ($products as $p) { ?>
    <tr<?php echo $p['active'] ? '' : ' style="color:#999"'; ?>>
        <td><input type="checkbox" name="ids[]" value="<?php echo h($p['id']); ?>"></td>
        <td><?php echo h($p['sku']); ?></td>
        <td><a href="<?php echo h(admin_url('product_edit', array('id' => $p['id']))); ?>"><?php echo h($p['name']); ?></a></td>
        <td><?php echo h($p['supplier_name']); ?></td>
        <td class="num"><?php echo h($p['price_fmt']); ?></td>
        <td class="num"><?php echo $p['on_hand'] === null ? '–' : (int) $p['on_hand']; ?></td>
        <td class="num"><?php echo (int) $p['reserved']; ?></td>
        <td><?php echo yes_no($p['active']); ?></td>
        <td><a href="<?php echo h(admin_url('product_toggle', array('id' => $p['id']))); ?>"><?php echo $p['active'] ? 'skrýt' : 'zobrazit'; ?></a></td>
    </tr>
<?php } ?>
</table>
<p>Změnit cenu vybraných o <input type="text" name="percent" size="4"> % <input type="submit" value="Provést"></p>
</form>
