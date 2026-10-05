<?php
/**
 * Nastaveni (tabulka settings: name => value).
 */

global $db;
legacy_db();
auth_require('admin');

// znama nastaveni a popisky; ostatni se zobrazi taky, ale bez popisku
$znama = array(
    'shop_open'          => 'Obchod otevřen (1/0)',
    'free_shipping_from' => 'Doprava zdarma od (Kč)',
    'shipping_price'     => 'Cena dopravy (Kč)',
    'invoice_footer'     => 'Patička faktury',
    'bank_account'       => 'Číslo účtu',
    'low_stock_notified' => 'Poslední upozornění na sklad (cron)',
    'maintenance_msg'    => 'Hláška při údržbě',
);

$zprava = '';

if (is_post()) {
    $hodnoty = post_param('settings', array());
    if (is_array($hodnoty)) {
        foreach ($hodnoty as $name => $value) {
            settings_set($name, $value);
        }
    }
    $nove = post_param('new_name');
    if ($nove != '') {
        settings_set($nove, post_param('new_value'));
    }
    cache_clear();
    audit_log('settings', '', 'update', is_array($hodnoty) ? $hodnoty : array());
    $zprava = 'Uloženo';
}

if (get_param('clear_cache') == '1') {
    $n = cache_clear();
    $zprava = 'Cache smazána (' . $n . ' souborů)';
}

$vsechna = array();
foreach ($db->query("SELECT name, value FROM settings ORDER BY name") as $r) {
    $vsechna[$r['name']] = $r['value'];
}
foreach ($znama as $k => $v) {
    if (!isset($vsechna[$k])) {
        $vsechna[$k] = '';
    }
}
ksort($vsechna);

$pageTitle = 'Nastavení';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Nastavení</h1>
<?php if ($zprava != '') { ?><p class="msg"><?php echo h($zprava); ?></p><?php } ?>
<form method="post">
<table class="grid" style="width:auto">
<?php foreach ($vsechna as $name => $value) { ?>
    <tr>
        <th><?php echo h(isset($znama[$name]) ? $znama[$name] : $name); ?></th>
        <td><input type="text" name="settings[<?php echo h($name); ?>]" value="<?php echo h($value); ?>" size="50"></td>
    </tr>
<?php } ?>
    <tr><th><input type="text" name="new_name" placeholder="nový klíč"></th><td><input type="text" name="new_value" size="50"></td></tr>
</table>
<p><input type="submit" value="Uložit"></p>
</form>
<p><a href="<?php echo h(admin_url('settings', array('clear_cache' => 1))); ?>">Smazat cache</a></p>
<p class="hint">Verze administrace: <?php echo h(LEGACY_VERSION); ?>, PHP <?php echo h(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION); ?></p>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
