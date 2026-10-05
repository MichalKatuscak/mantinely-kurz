<?php
/**
 * Kurzy men pro prepocet trzeb (tabulka exchange_rates).
 * Automaticke stahovani z CNB nefunguje od 2019 – kurzy se zadavaji rucne.
 *
 * POZOR: kurz se pouzije i zpetne na vsechny starsi mesice v reportu trzeb!
 */

global $db;
legacy_db();
auth_require('ucetni');

$zprava = '';

if (is_post()) {
    $kurzy = post_param('rate', array());
    if (is_array($kurzy)) {
        foreach ($kurzy as $mena => $kurz) {
            $kurz = (float) str_replace(',', '.', (string) $kurz);
            if ($kurz <= 0) {
                continue;
            }
            $existuje = $db->one("SELECT currency FROM exchange_rates WHERE currency = '" . $mena . "'");
            if ($existuje) {
                $db->exec("UPDATE exchange_rates SET rate_to_czk = " . $kurz . ", updated_at = '" . date('Y-m-d H:i:s') . "' WHERE currency = '" . $mena . "'");
            } else {
                $db->exec("INSERT INTO exchange_rates (currency, rate_to_czk, updated_at) VALUES ('" . $mena . "', " . $kurz . ", '" . date('Y-m-d H:i:s') . "')");
            }
            audit_log('exchange_rate', $mena, 'update', array('rate' => $kurz));
        }
    }
    cache_clear();
    $zprava = 'Kurzy uloženy';
}

$kurzy = $db->query("SELECT * FROM exchange_rates ORDER BY currency");

$pageTitle = 'Kurzy měn';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Kurzy měn</h1>
<?php if ($zprava != '') { ?><p class="msg"><?php echo h($zprava); ?></p><?php } ?>
<form method="post">
<table class="grid" style="width:auto">
    <tr><th>Měna</th><th>Kurz (Kč za 1 jednotku)</th><th>Změněno</th></tr>
<?php foreach ($kurzy as $k) { ?>
    <tr>
        <td><?php echo h($k['currency']); ?></td>
        <td><input type="text" name="rate[<?php echo h($k['currency']); ?>]" value="<?php echo h($k['rate_to_czk']); ?>" size="8"></td>
        <td><?php echo format_date($k['updated_at'], true); ?></td>
    </tr>
<?php } ?>
</table>
<p><input type="submit" value="Uložit"></p>
</form>
<p class="hint">Kurz platí pro všechna období (historie kurzů se neukládá).</p>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
