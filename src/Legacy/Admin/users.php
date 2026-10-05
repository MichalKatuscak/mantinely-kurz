<?php
/**
 * Sprava uzivatelu administrace – stara verze (pred UserController).
 * Porad se pouziva na reset hesla, protoze UserController to neumi poslat mailem.
 */

global $db;
legacy_db();
auth_require('admin');

$akce = get_param('akce');
$zprava = '';

if ($akce == 'reset') {
    $uid = (int) get_param('id');
    $u = $db->one("SELECT * FROM admin_users WHERE id = " . $uid);
    if ($u) {
        $noveHeslo = generate_password(10);
        $db->exec("UPDATE admin_users SET password_md5 = '" . md5($noveHeslo) . "' WHERE id = " . $uid);
        // login je casto e-mail, tak to zkusime poslat
        if (is_email($u['login'])) {
            send_mail($u['login'], 'Nové heslo do administrace', 'Vaše nové heslo: ' . $noveHeslo);
            $zprava = 'Nové heslo odesláno na ' . $u['login'];
        } else {
            $zprava = 'Nové heslo pro ' . $u['login'] . ': ' . $noveHeslo;
        }
        audit_log('admin_user', $uid, 'reset_password');
    }
}

if ($akce == 'smazat') {
    $uid = (int) get_param('id');
    $db->exec("DELETE FROM admin_users WHERE id = " . $uid);
    audit_log('admin_user', $uid, 'delete');
    $zprava = 'Uživatel smazán';
}

$uzivatele = $db->query("SELECT * FROM admin_users ORDER BY role, login");

$pageTitle = 'Uživatelé';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Uživatelé (stará správa)</h1>
<?php if ($zprava != '') { ?><p class="msg"><?php echo h($zprava); ?></p><?php } ?>
<p><a href="<?php echo h(admin_url('user_list')); ?>">Nová správa uživatelů »</a></p>
<table class="grid">
    <tr><th>ID</th><th>Login</th><th>Role</th><th>Poslední přihlášení</th><th>Akce</th></tr>
<?php foreach ($uzivatele as $u) { ?>
    <tr>
        <td><?php echo (int) $u['id']; ?></td>
        <td><?php echo h($u['login']); ?></td>
        <td><?php echo h($u['role']); ?></td>
        <td><?php echo $u['last_login'] ? datum($u['last_login']) : 'nikdy'; ?></td>
        <td>
            <a href="<?php echo h(admin_url('users', array('akce' => 'reset', 'id' => $u['id']))); ?>" onclick="return confirm('Resetovat heslo?')">reset hesla</a> |
            <a href="<?php echo h(admin_url('users', array('akce' => 'smazat', 'id' => $u['id']))); ?>" onclick="return confirm('Smazat?')">smazat</a>
        </td>
    </tr>
<?php } ?>
</table>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
