<?php
/**
 * Nezaplacene objednavky – prehled pro obchod, aby mohli urgovat
 * pred tim, nez je cron po 14 dnech stornuje.
 */

global $db;
legacy_db();
auth_require('obchod');

$dny = (int) config('unpaid_days', 14);

// urgence – posle mail zakaznikovi
if (get_param('urgovat') != '') {
    $oid = get_param('urgovat');
    $o = $db->one("SELECT o.id, o.currency, c.email, c.name FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE o.id = '" . $oid . "'");
    if ($o && $o['email'] !== null) {
        $castka = order_total_after_discount($oid);
        send_mail($o['email'], 'Připomínka platby', "Dobrý den,\n\nevidujeme nezaplacenou objednávku " . $oid . " na částku "
            . format_price($castka, $o['currency']) . ".\nPokud nebude zaplacena, bude automaticky stornována.\n");
        $db->exec("INSERT INTO order_notes (order_id, author, note, created_at) VALUES ('" . $oid . "', '" . auth_login_name() . "', 'Odeslána urgence platby', '" . date('Y-m-d H:i:s') . "')");
        flash('Urgence odeslána na ' . $o['email']);
    } else {
        flash('Zákazník nemá e-mail v customers', 'error');
    }
}

$objednavky = $db->query("SELECT o.id, o.customer_id, o.placed_at, o.currency, o.discount_amount_in_cents,"
    . " (SELECT SUM(quantity * unit_price_amount_in_cents) FROM order_items WHERE order_id = o.id) AS total,"
    . " (SELECT COUNT(*) FROM order_notes n WHERE n.order_id = o.id AND n.note LIKE 'Odeslána urgence%') AS urgenci"
    . " FROM orders o WHERE o.status = 'confirmed' ORDER BY o.placed_at");

$pageTitle = 'Nezaplacené objednávky';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Nezaplacené objednávky</h1>
<p class="hint">Objednávky starší než <?php echo $dny; ?> dní stornuje cron automaticky.</p>
<?php foreach (flash_messages() as $f) { ?><p class="<?php echo $f['type'] == 'error' ? 'err' : 'msg'; ?>"><?php echo h($f['msg']); ?></p><?php } ?>
<table class="grid">
    <tr><th>Objednávka</th><th>Zákazník</th><th>Datum</th><th class="num">Dní</th><th class="num">K úhradě</th><th>Urgencí</th><th></th></tr>
<?php foreach ($objednavky as $o) { ?>
    <?php $stari = $o['placed_at'] !== null ? (int) floor((time() - strtotime($o['placed_at'])) / 86400) : 0; ?>
    <tr<?php echo $stari >= $dny - 3 ? ' class="low"' : ''; ?>>
        <td><a href="<?php echo h(admin_url('order', array('id' => $o['id']))); ?>"><?php echo h(substr($o['id'], 0, 8)); ?>…</a></td>
        <td><?php echo h(customer_name($o['customer_id'])); ?></td>
        <td><?php echo format_date($o['placed_at']); ?></td>
        <td class="num"><?php echo $stari; ?></td>
        <td class="num"><?php echo format_price(max(0, (int) $o['total'] - (int) $o['discount_amount_in_cents']), $o['currency']); ?></td>
        <td class="num"><?php echo (int) $o['urgenci']; ?></td>
        <td><a href="<?php echo h(admin_url('unpaid_orders', array('urgovat' => $o['id']))); ?>">urgovat</a></td>
    </tr>
<?php } ?>
</table>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
