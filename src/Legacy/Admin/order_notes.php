<?php
/**
 * Interni poznamky k objednavce.
 * ?order=<uuid>
 */

global $db;
legacy_db();
auth_require();

$orderId = get_param('order');
if ($orderId == '') {
    $orderId = post_param('order');
}

$objednavka = $db->one("SELECT id, status, customer_id FROM orders WHERE id = '" . $orderId . "'");
if ($objednavka === null) {
    echo '<p>Objednávka nenalezena</p>';
    return;
}

if (is_post()) {
    csrf_check();
    $text = post_param('note');
    if ($text != '') {
        $db->exec("INSERT INTO order_notes (order_id, author, note, created_at) VALUES ('" . $orderId . "', '"
            . auth_login_name() . "', '" . db_escape_old($text) . "', '" . date('Y-m-d H:i:s') . "')");
        audit_log('order', $orderId, 'note');
    }
    $smazat = (int) post_param('delete', 0);
    if ($smazat > 0) {
        $db->exec("DELETE FROM order_notes WHERE id = " . $smazat);
    }
    redirect(admin_url('order_notes', array('order' => $orderId)));
    return;
}

$poznamky = $db->query("SELECT * FROM order_notes WHERE order_id = '" . $orderId . "' ORDER BY created_at DESC");

$pageTitle = 'Poznámky';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Poznámky k objednávce <?php echo h($orderId); ?></h1>
<p>Stav: <?php echo h(order_state_label($objednavka['status'])); ?>, zákazník: <?php echo h(customer_name($objednavka['customer_id'])); ?></p>

<form method="post"><?php echo csrf_field(); ?>
    <input type="hidden" name="order" value="<?php echo h($orderId); ?>">
    <textarea name="note" rows="4" cols="70"></textarea><br>
    <input type="submit" value="Přidat poznámku">
</form>

<?php foreach ($poznamky as $p) { ?>
<div style="border-bottom:1px solid #ddd;padding:5px 0">
    <strong><?php echo h($p['author']); ?></strong> – <?php echo format_date($p['created_at'], true); ?>
    <form method="post" style="display:inline" onsubmit="return confirm('Smazat poznámku?')"><?php echo csrf_field(); ?>
        <input type="hidden" name="order" value="<?php echo h($orderId); ?>">
        <input type="hidden" name="delete" value="<?php echo (int) $p['id']; ?>">
        <input type="submit" value="×">
    </form>
    <br><?php echo nl2br(h($p['note'])); ?>
</div>
<?php } ?>
<p><a href="<?php echo h(admin_url('order', array('id' => $orderId))); ?>">« objednávka</a></p>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
