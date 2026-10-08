<?php
/**
 * Dodavatele. Import cenniku z CSV rozdelany (2018), viz csv_parse().
 */

global $db;
legacy_db();
auth_require('sklad');

$zprava = '';

if (is_post()) {
    csrf_check();
    $akce = post_param('akce');
    if ($akce == 'ulozit') {
        $sid = (int) post_param('id', 0);
        $nazev = post_param('name');
        $email = post_param('email');
        $ico = post_param('ico');
        if ($nazev == '') {
            $zprava = 'Chybí název';
        } elseif ($sid > 0) {
            $db->exec("UPDATE suppliers SET name = '" . db_escape_old($nazev) . "', email = '" . db_escape_old($email) . "', ico = '" . db_escape_old($ico) . "' WHERE id = " . $sid);
            $zprava = 'Uloženo';
        } else {
            $db->exec("INSERT INTO suppliers (name, email, ico, created_at) VALUES ('" . db_escape_old($nazev) . "', '" . db_escape_old($email) . "', '" . db_escape_old($ico) . "', '" . date('Y-m-d H:i:s') . "')");
            $zprava = 'Dodavatel přidán';
        }
    } elseif ($akce == 'import') {
        // TODO: dodelat import cenniku
        $obsah = post_param('csv');
        $radky = csv_parse($obsah);
        $zprava = 'Import zatím není hotový (načteno řádků: ' . count($radky) . ')';
        /*
        foreach ($radky as $r) {
            // sku;nazev;cena
            $db->exec("UPDATE products SET price_cents = " . kc2hal($r[2]) . " WHERE sku = '" . $r[0] . "'");
        }
        */
    }
}

$dodavatele = $db->query("SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id) AS produktu FROM suppliers s ORDER BY s.name");

$pageTitle = 'Dodavatelé';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Dodavatelé</h1>
<?php if ($zprava != '') { ?><p class="msg"><?php echo h($zprava); ?></p><?php } ?>
<table class="grid">
    <tr><th>Název</th><th>E-mail</th><th>IČO</th><th class="num">Produktů</th><th></th></tr>
<?php foreach ($dodavatele as $d) { ?>
    <tr>
        <form method="post"><?php echo csrf_field(); ?>
        <td><input type="hidden" name="akce" value="ulozit"><input type="hidden" name="id" value="<?php echo (int) $d['id']; ?>"><input type="text" name="name" value="<?php echo h($d['name']); ?>"></td>
        <td><input type="text" name="email" value="<?php echo h($d['email']); ?>"></td>
        <td><input type="text" name="ico" value="<?php echo h($d['ico']); ?>" size="10"></td>
        <td class="num"><a href="<?php echo h(admin_url('products', array('supplier' => $d['id'], 'active' => ''))); ?>"><?php echo (int) $d['produktu']; ?></a></td>
        <td><input type="submit" value="Uložit"></td>
        </form>
    </tr>
<?php } ?>
    <tr>
        <form method="post"><?php echo csrf_field(); ?>
        <td><input type="hidden" name="akce" value="ulozit"><input type="text" name="name" placeholder="nový dodavatel"></td>
        <td><input type="text" name="email"></td>
        <td><input type="text" name="ico" size="10"></td>
        <td></td>
        <td><input type="submit" value="Přidat"></td>
        </form>
    </tr>
</table>

<h2>Import ceníku (rozpracováno)</h2>
<form method="post"><?php echo csrf_field(); ?>
    <input type="hidden" name="akce" value="import">
    <textarea name="csv" rows="6" cols="70" placeholder="sku;název;cena"></textarea><br>
    <input type="submit" value="Načíst">
</form>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
