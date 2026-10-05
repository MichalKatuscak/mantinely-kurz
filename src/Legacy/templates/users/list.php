<?php
/**
 * Uzivatele administrace.
 * Promenne: $users, $roles
 */
$roleOptions = array();
foreach ($roles as $r) {
    $roleOptions[$r] = $r;
}
?>
<h1>Uživatelé administrace</h1>
<table class="grid">
    <tr><th>Login</th><th>Role</th><th>Poslední přihlášení</th><th></th></tr>
<?php foreach ($users as $u) { ?>
    <tr>
        <form method="post" action="<?php echo h(admin_url('user_save')); ?>">
        <td><input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>"><input type="text" name="login" value="<?php echo h($u['login']); ?>"></td>
        <td><select name="role"><?php echo select_options($roleOptions, $u['role']); ?></select></td>
        <td><?php echo format_date($u['last_login'], true); ?></td>
        <td>nové heslo: <input type="password" name="password" size="10"> <input type="submit" value="Uložit"></td>
        </form>
    </tr>
<?php } ?>
    <tr>
        <form method="post" action="<?php echo h(admin_url('user_save')); ?>">
        <td><input type="text" name="login" placeholder="nový login"></td>
        <td><select name="role"><?php echo select_options($roleOptions, 'obchod'); ?></select></td>
        <td></td>
        <td>heslo: <input type="password" name="password" size="10"> <input type="submit" value="Přidat"></td>
        </form>
    </tr>
</table>
