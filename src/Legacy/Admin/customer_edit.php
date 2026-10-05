<?php
/**
 * Novy zakaznik / uprava zakaznika.
 * ?id=<uuid> (bez id = novy)
 */

global $db;
legacy_db();
auth_require('obchod');

$id = get_param('id');
if ($id == '') {
    $id = post_param('id');
}

$zakaznik = array('id' => '', 'email' => '', 'name' => '', 'phone' => '', 'newsletter' => 0, 'note' => '', 'created_at' => null);
if ($id != '') {
    $row = $db->one("SELECT * FROM customers WHERE id = '" . $id . "'");
    if ($row) {
        $zakaznik = $row;
    }
}

$chyby = array();

if (is_post()) {
    $zakaznik['email'] = strtolower(post_param('email'));
    $zakaznik['name'] = post_param('name');
    $zakaznik['phone'] = preg_replace('/\s+/', '', (string) post_param('phone'));
    $zakaznik['newsletter'] = post_param('newsletter') ? 1 : 0;
    $zakaznik['note'] = post_param('note');

    if (!is_email($zakaznik['email'])) {
        $chyby[] = 'Neplatný e-mail';
    }
    // duplicita e-mailu
    $dup = $db->one("SELECT id FROM customers WHERE email = '" . db_escape_old($zakaznik['email']) . "' AND id != '" . $id . "'");
    if ($dup) {
        $chyby[] = 'Zákazník s tímto e-mailem už existuje';
    }
    if ($zakaznik['phone'] != '' && !preg_match('/^\+?[0-9]{9,12}$/', $zakaznik['phone'])) {
        $chyby[] = 'Neplatný telefon';
    }

    if (count($chyby) == 0) {
        if ($zakaznik['id'] != '') {
            $db->exec("UPDATE customers SET"
                . " email = '" . db_escape_old($zakaznik['email']) . "',"
                . " name = '" . db_escape_old($zakaznik['name']) . "',"
                . " phone = '" . db_escape_old($zakaznik['phone']) . "',"
                . " newsletter = " . (int) $zakaznik['newsletter'] . ","
                . " note = '" . db_escape_old($zakaznik['note']) . "'"
                . " WHERE id = '" . $zakaznik['id'] . "'");
            audit_log('customer', $zakaznik['id'], 'update');
        } else {
            $zakaznik['id'] = db_uuid();
            db_insert('customers', array(
                'id'         => $zakaznik['id'],
                'email'      => $zakaznik['email'],
                'name'       => $zakaznik['name'],
                'phone'      => $zakaznik['phone'],
                'newsletter' => $zakaznik['newsletter'],
                'note'       => $zakaznik['note'],
                'created_at' => db_now(),
            ));
            audit_log('customer', $zakaznik['id'], 'create');
        }
        flash('Zákazník uložen');
        redirect(admin_url('customer', array('id' => $zakaznik['id'])));
        return;
    }
}

$pageTitle = $zakaznik['id'] != '' ? 'Úprava zákazníka' : 'Nový zákazník';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1><?php echo h($pageTitle); ?></h1>
<?php foreach ($chyby as $ch) { ?><p class="err"><?php echo h($ch); ?></p><?php } ?>
<form method="post">
<input type="hidden" name="id" value="<?php echo h($zakaznik['id']); ?>">
<table class="grid" style="width:auto">
    <tr><th>E-mail *</th><td><input type="text" name="email" value="<?php echo h($zakaznik['email']); ?>" size="40"></td></tr>
    <tr><th>Jméno</th><td><input type="text" name="name" value="<?php echo h($zakaznik['name']); ?>" size="40"></td></tr>
    <tr><th>Telefon</th><td><input type="text" name="phone" value="<?php echo h($zakaznik['phone']); ?>"></td></tr>
    <tr><th>Newsletter</th><td><input type="checkbox" name="newsletter" value="1"<?php echo $zakaznik['newsletter'] ? ' checked' : ''; ?>></td></tr>
    <tr><th>Poznámka</th><td><textarea name="note" rows="4" cols="50"><?php echo h($zakaznik['note']); ?></textarea></td></tr>
</table>
<p><input type="submit" value="Uložit"></p>
</form>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
