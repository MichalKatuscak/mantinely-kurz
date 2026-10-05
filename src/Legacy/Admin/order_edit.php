<?php
/**
 * Uprava objednavky – zmena stavu a slevy.
 *
 * ?id=<uuid>
 *
 * Puvodne (2015) se tu dalo upravovat vsechno vcetne polozek, od 2024
 * objednavky zaklada novy e-shop, takze tu zustal jen stav a sleva.
 * Obchodaci to pouzivaji, kdyz zakaznik zaplati prevodem a nic se nesparuje.
 */

global $db;
legacy_db();
auth_require('obchod');

$id = get_param('id');
if ($id == '') {
    $id = post_param('id');
}

$order = $db->one("SELECT * FROM orders WHERE id = '" . $id . "'");
if ($order === null) {
    echo '<p class="err">Objednávka nenalezena.</p>';
    return;
}

$errors = array();
$msg = '';

if (is_post()) {
    $newStatus = post_param('status');
    $discount = post_param('discount');

    // --- zmena stavu ---
    if ($newStatus != '' && $newStatus != $order['status']) {
        if (!isset($GLOBALS['ORDER_STATES'][$newStatus])) {
            $errors[] = 'Neplatný stav';
        } else {
            // obchodaci obcas vraceji stornovane zpet – zakazano (2016, po incidentu)
            if ($order['status'] == 'cancelled' && !auth_has_role('admin')) {
                $errors[] = 'Stornovanou objednávku může obnovit jen admin';
            } else {
                $db->exec("UPDATE orders SET status = '" . $newStatus . "' WHERE id = '" . $id . "'");

                // pri potvrzeni doplnit datum, pokud chybi (stare objednavky)
                if ($newStatus == 'confirmed' && $order['placed_at'] === null) {
                    $db->exec("UPDATE orders SET placed_at = '" . date('Y-m-d H:i:s') . "' WHERE id = '" . $id . "'");
                }

                audit_log('order', $id, 'status', array('from' => $order['status'], 'to' => $newStatus));

                // mail zakaznikovi pri odeslani
                if ($newStatus == 'shipped') {
                    $c = $db->one("SELECT email FROM customers WHERE id = '" . $order['customer_id'] . "'");
                    if ($c) {
                        send_mail($c['email'], 'Vaše objednávka byla odeslána', "Dobrý den,\n\nvaše objednávka " . $id . " byla právě odeslána.\n");
                    }
                }
                // TODO: pri 'cancelled' uvolnit rezervace ve skladu (cron to taky nedela)
                $msg .= 'Stav změněn na ' . order_state_label($newStatus) . '. ';
            }
        }
    }

    // --- sleva ---
    if ($discount !== '') {
        $discountCents = kc2hal($discount);
        if ($discountCents < 0) {
            $errors[] = 'Sleva nemůže být záporná';
        } elseif ($discountCents != (int) $order['discount_amount_in_cents']) {
            $sum = order_total($id);
            if ($discountCents > $sum) {
                // sleva vetsi nez objednavka – povolime, ale upozornime (pozadavek obchodu 2017)
                $msg .= 'Pozor: sleva je vyšší než hodnota objednávky! ';
            }
            $db->exec("UPDATE orders SET discount_amount_in_cents = " . $discountCents . ", discount_currency = '" . $order['currency'] . "' WHERE id = '" . $id . "'");
            audit_log('order', $id, 'discount', array('from' => (int) $order['discount_amount_in_cents'], 'to' => $discountCents));
            $msg .= 'Sleva uložena. ';
        }
    }

    // poznamka k uprave
    $note = post_param('note');
    if ($note != '') {
        $db->exec("INSERT INTO order_notes (order_id, author, note, created_at) VALUES ('" . $id . "', '" . auth_login_name() . "', " . $db->quote($note) . ", '" . date('Y-m-d H:i:s') . "')");
    }

    cache_delete('dashboard_stats');

    // znovu nacist
    $order = $db->one("SELECT * FROM orders WHERE id = '" . $id . "'");
}

$items = $db->query("SELECT i.*, p.name FROM order_items i LEFT JOIN products p ON p.id = i.product_id WHERE i.order_id = '" . $id . "'");
$sum = order_total($id);

$pageTitle = 'Úprava objednávky';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Úprava objednávky <?php echo h($order['id']); ?></h1>

<?php if ($msg != '') { ?><p class="msg"><?php echo h($msg); ?></p><?php } ?>
<?php foreach ($errors as $e) { ?><p class="err"><?php echo h($e); ?></p><?php } ?>

<form method="post">
<input type="hidden" name="id" value="<?php echo h($order['id']); ?>">
<table class="grid" style="width:auto">
    <tr><th>Zákazník</th><td><?php echo h(customer_name($order['customer_id'])); ?></td></tr>
    <tr><th>Datum</th><td><?php echo format_date($order['placed_at'], true); ?></td></tr>
    <tr><th>Stav</th><td>
        <select name="status">
        <?php foreach ($GLOBALS['ORDER_STATES'] as $k => $v) { ?>
            <option value="<?php echo h($k); ?>"<?php echo $k == $order['status'] ? ' selected' : ''; ?>><?php echo h($v); ?></option>
        <?php } ?>
        </select>
    </td></tr>
    <tr><th>Hodnota položek</th><td><?php echo format_price($sum, $order['currency']); ?></td></tr>
    <tr><th>Sleva (<?php echo h($order['currency']); ?>)</th><td><input type="text" name="discount" value="<?php echo h(number_format(((int) $order['discount_amount_in_cents']) / 100, 2, ',', '')); ?>" size="10"></td></tr>
    <tr><th>Poznámka</th><td><textarea name="note" rows="3" cols="50"></textarea></td></tr>
</table>
<p><input type="submit" value="Uložit"></p>
</form>

<h2>Položky</h2>
<table class="grid">
<?php foreach ($items as $it) { ?>
    <tr>
        <td><?php echo h($it['name'] !== null ? $it['name'] : $it['product_id']); ?></td>
        <td class="num"><?php echo (int) $it['quantity']; ?> ks</td>
        <td class="num"><?php echo format_price($it['unit_price_amount_in_cents'], $it['unit_price_currency']); ?></td>
    </tr>
<?php } ?>
</table>
<p><a href="<?php echo h(admin_url('order', array('id' => $order['id']))); ?>">« detail objednávky</a></p>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
