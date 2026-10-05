<?php
/**
 * Prihlaseni do administrace.
 * Od 2024 se prihlasuje pres Symfony, tahle stranka zustala pro "nouzovy" pristup.
 */

global $db;
legacy_db();

$chyba = '';

if (is_post()) {
    $login = post_param('login');
    $heslo = post_param('heslo');

    // ochrana proti hadani hesla: max 5 pokusu za 10 minut z jedne IP
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'cli';
    $pokusy = (int) $db->value("SELECT COUNT(*) FROM audit_log WHERE entity = 'login_fail' AND entity_id = '" . $ip . "' AND created_at > '" . date('Y-m-d H:i:s', strtotime('-10 minutes')) . "'");

    if ($pokusy >= 5) {
        $chyba = 'Příliš mnoho pokusů, zkuste to za 10 minut.';
    } elseif (auth_login($login, $heslo)) {
        redirect(admin_url('dashboard'));
        return;
    } else {
        audit_log('login_fail', $ip, 'fail', array('login' => $login));
        $chyba = 'Špatné jméno nebo heslo';
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Přihlášení | Administrace</title>
    <style>
        body { font-family: Verdana, Arial, sans-serif; font-size: 12px; background: #2b4a6b; }
        #login { width: 300px; margin: 100px auto; background: #fff; padding: 20px; border: 1px solid #ccc; }
        .err { color: #c00; }
    </style>
</head>
<body>
<div id="login">
    <h1><?php echo h(config('shop_name')); ?></h1>
    <?php if ($chyba != '') { ?><p class="err"><?php echo h($chyba); ?></p><?php } ?>
    <form method="post">
        <p>Login:<br><input type="text" name="login" value="<?php echo h(post_param('login')); ?>"></p>
        <p>Heslo:<br><input type="password" name="heslo"></p>
        <p><input type="submit" value="Přihlásit"></p>
    </form>
    <!-- zapomenute heslo: napiste Petrovi -->
</div>
</body>
</html>
