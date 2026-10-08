<?php
/**
 * Novy produkt / uprava produktu.
 * ?id=<uuid>
 *
 * Cena se zadava v korunach, uklada v halerich (price_cents).
 * Novy e-shop cte products primo (jen aktivni), takze zmena ceny
 * se projevi okamzite i u rozpracovanych objednavek. (FIXME?)
 */

use App\Legacy\lib\PriceUtils;

global $db;
legacy_db();
auth_require('obchod');

$id = get_param('id');
if ($id == '') {
    $id = post_param('id');
}

$produkt = array(
    'id' => '', 'sku' => '', 'name' => '', 'description' => '', 'price_cents' => 0,
    'currency' => 'CZK', 'active' => 1, 'supplier_id' => null, 'created_at' => null,
);
if ($id != '') {
    $row = $db->one("SELECT * FROM products WHERE id = '" . $id . "'");
    if ($row) {
        $produkt = $row;
    }
}

$chyby = array();

if (is_post()) {
    csrf_check();
    $produkt['sku'] = strtoupper(post_param('sku'));
    $produkt['name'] = post_param('name');
    $produkt['description'] = post_param('description');
    $produkt['price_cents'] = PriceUtils::parse(post_param('price'));
    $produkt['currency'] = post_param('currency', 'CZK');
    $produkt['active'] = post_param('active') ? 1 : 0;
    $produkt['supplier_id'] = post_param('supplier_id') !== '' ? (int) post_param('supplier_id') : null;

    if ($produkt['name'] == '') {
        $chyby[] = 'Chybí název';
    }
    if ($produkt['price_cents'] <= 0) {
        $chyby[] = 'Cena musí být kladná';
    }
    if (!in_array($produkt['currency'], $GLOBALS['CURRENCIES'])) {
        $chyby[] = 'Neplatná měna';
    }
    if ($produkt['sku'] != '') {
        $dup = $db->one("SELECT id FROM products WHERE sku = '" . $produkt['sku'] . "' AND id != '" . $produkt['id'] . "'");
        if ($dup) {
            $chyby[] = 'SKU už existuje';
        }
    }

    if (count($chyby) == 0) {
        if ($produkt['id'] != '') {
            $stara = $db->one("SELECT price_cents FROM products WHERE id = '" . $produkt['id'] . "'");
            $db->exec("UPDATE products SET"
                . " sku = '" . $produkt['sku'] . "',"
                . " name = " . $db->quote($produkt['name']) . ","
                . " description = " . $db->quote($produkt['description']) . ","
                . " price_cents = " . (int) $produkt['price_cents'] . ","
                . " currency = '" . $produkt['currency'] . "',"
                . " active = " . (int) $produkt['active'] . ","
                . " supplier_id = " . ($produkt['supplier_id'] === null ? 'NULL' : (int) $produkt['supplier_id'])
                . " WHERE id = '" . $produkt['id'] . "'");
            audit_log('product', $produkt['id'], 'update', array('price_from' => $stara ? (int) $stara['price_cents'] : null, 'price_to' => (int) $produkt['price_cents']));
        } else {
            $produkt['id'] = db_uuid();
            $db->exec("INSERT INTO products (id, sku, name, description, price_cents, currency, active, supplier_id, created_at) VALUES ("
                . "'" . $produkt['id'] . "', '" . $produkt['sku'] . "', " . $db->quote($produkt['name']) . ", " . $db->quote($produkt['description']) . ", "
                . (int) $produkt['price_cents'] . ", '" . $produkt['currency'] . "', " . (int) $produkt['active'] . ", "
                . ($produkt['supplier_id'] === null ? 'NULL' : (int) $produkt['supplier_id']) . ", '" . date('Y-m-d H:i:s') . "')");
            // skladova karta – driv jsme ji zakladali tady, ted ji zaklada novy e-shop (?)
            // $db->exec("INSERT INTO stock_items (product_id, on_hand, reservations) VALUES ('" . $produkt['id'] . "', 0, '{}')");
            audit_log('product', $produkt['id'], 'create');
        }
        flash('Produkt uložen');
        redirect(admin_url('products'));
        return;
    }
}

$dodavatele = array('' => '– žádný –');
foreach ($db->query("SELECT id, name FROM suppliers ORDER BY name") as $s) {
    $dodavatele[$s['id']] = $s['name'];
}
$meny = array();
foreach ($GLOBALS['CURRENCIES'] as $c) {
    $meny[$c] = $c;
}

$pageTitle = $produkt['id'] != '' ? 'Úprava produktu' : 'Nový produkt';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1><?php echo h($pageTitle); ?></h1>
<?php foreach ($chyby as $ch) { ?><p class="err"><?php echo h($ch); ?></p><?php } ?>
<form method="post"><?php echo csrf_field(); ?>
<input type="hidden" name="id" value="<?php echo h($produkt['id']); ?>">
<table class="grid" style="width:auto">
    <tr><th>SKU</th><td><input type="text" name="sku" value="<?php echo h($produkt['sku']); ?>"></td></tr>
    <tr><th>Název *</th><td><input type="text" name="name" value="<?php echo h($produkt['name']); ?>" size="50"></td></tr>
    <tr><th>Popis</th><td><textarea name="description" rows="6" cols="60"><?php echo h($produkt['description']); ?></textarea></td></tr>
    <tr><th>Cena *</th><td><input type="text" name="price" value="<?php echo h(number_format(((int) $produkt['price_cents']) / 100, 2, ',', '')); ?>" size="10">
        <select name="currency"><?php echo select_options($meny, $produkt['currency']); ?></select></td></tr>
    <tr><th>Dodavatel</th><td><select name="supplier_id"><?php echo select_options($dodavatele, (string) $produkt['supplier_id']); ?></select></td></tr>
    <tr><th>Aktivní</th><td><input type="checkbox" name="active" value="1"<?php echo $produkt['active'] ? ' checked' : ''; ?>></td></tr>
</table>
<p><input type="submit" value="Uložit"></p>
</form>
<?php if ($produkt['id'] != '') { ?>
<p><a href="<?php echo h(admin_url('stock_reservations', array('product' => $produkt['id']))); ?>">Rezervace ve skladu</a></p>
<?php } ?>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
