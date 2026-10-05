<?php
/**
 * Newsletter – zarazeni mailu do fronty pro zakazniky s newsletter = 1.
 * Odesila cron (CronRunner::jobNewsletter), max 100 za hodinu.
 */

global $db;
legacy_db();
auth_require('obchod');

$zprava = '';

if (is_post()) {
    $predmet = post_param('subject');
    $text = post_param('body');
    $test = post_param('test');

    if ($predmet == '' || $text == '') {
        $zprava = 'Vyplňte předmět i text.';
    } elseif ($test != '') {
        // testovaci odeslani jen na zadany e-mail
        send_mail($test, '[TEST] ' . $predmet, $text);
        $zprava = 'Testovací e-mail odeslán na ' . $test;
    } else {
        $prijemci = $db->query("SELECT id, email, name FROM customers WHERE newsletter = 1 AND email != ''");
        $n = 0;
        foreach ($prijemci as $p) {
            // oslovení – {jmeno} v textu
            $osobni = str_replace('{jmeno}', $p['name'] != '' ? $p['name'] : 'zákazníku', $text);
            $osobni .= "\n\n--\nOdhlásit odběr: https://example.cz/odhlasit?e=" . urlencode($p['email']) . '&h=' . md5($p['email'] . 'sul2015');
            newsletter_enqueue($p['id'], $p['email'], $predmet, $osobni);
            $n++;
        }
        audit_log('newsletter', '', 'enqueue', array('subject' => $predmet, 'count' => $n));
        $zprava = 'Do fronty zařazeno: ' . $n;
    }
}

if (get_param('smazat_frontu') == '1') {
    $db->exec("DELETE FROM newsletter_queue WHERE sent_at IS NULL");
    $zprava = 'Fronta smazána';
}

$fronta = (int) $db->value("SELECT COUNT(*) FROM newsletter_queue WHERE sent_at IS NULL");
$odeslano = (int) $db->value("SELECT COUNT(*) FROM newsletter_queue WHERE sent_at IS NOT NULL");
$odberatelu = (int) $db->value("SELECT COUNT(*) FROM customers WHERE newsletter = 1");
$posledni = $db->query("SELECT subject, COUNT(*) AS c, MIN(created_at) AS created FROM newsletter_queue GROUP BY subject ORDER BY created DESC LIMIT 10");

$pageTitle = 'Newsletter';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Newsletter</h1>
<?php if ($zprava != '') { ?><p class="msg"><?php echo h($zprava); ?></p><?php } ?>
<p>Odběratelů: <strong><?php echo $odberatelu; ?></strong>, ve frontě: <strong><?php echo $fronta; ?></strong>, odesláno celkem: <?php echo $odeslano; ?>
<?php if ($fronta > 0) { ?> | <a href="<?php echo h(admin_url('newsletter', array('smazat_frontu' => 1))); ?>" onclick="return confirm('Smazat neodeslané?')">smazat frontu</a><?php } ?></p>

<form method="post">
<table class="grid" style="width:auto">
    <tr><th>Předmět</th><td><input type="text" name="subject" size="60"></td></tr>
    <tr><th>Text</th><td><textarea name="body" rows="12" cols="70">Dobrý den, {jmeno},

</textarea></td></tr>
    <tr><th>Test na e-mail</th><td><input type="text" name="test" size="30"> <span class="hint">(vyplněno = pošle se jen sem)</span></td></tr>
</table>
<p><input type="submit" value="Odeslat"></p>
</form>

<h2>Poslední rozesílky</h2>
<table class="grid">
<?php foreach ($posledni as $p) { ?>
    <tr><td><?php echo h($p['subject']); ?></td><td class="num"><?php echo (int) $p['c']; ?></td><td><?php echo format_date($p['created'], true); ?></td></tr>
<?php } ?>
</table>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
