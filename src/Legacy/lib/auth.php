<?php
/**
 * Prihlasovani do administrace.
 *
 * Hesla jsou MD5 (2014). FIXME: prejit na password_hash, az bude cas.
 * Od 2024 pred administraci stoji Symfony firewall (prihlaseni, ROLE_STAFF).
 * Prihlaseneho uzivatele s roli predava LegacyFrontController do
 * $GLOBALS['LEGACY_USER'], auth_require() podle nej kontroluje roli.
 */

use App\Legacy\lib\AccessDenied;

function auth_login($login, $password)
{
    global $db;
    legacy_db();
    $user = $db->one("SELECT * FROM admin_users WHERE login = '" . $login . "' AND password_md5 = '" . md5((string) $password) . "'");
    if ($user === null) {
        return false;
    }
    $db->exec("UPDATE admin_users SET last_login = '" . date('Y-m-d H:i:s') . "' WHERE id = " . (int) $user['id']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_login'] = $user['login'];
        $_SESSION['admin_role'] = $user['role'];
    }
    $GLOBALS['LEGACY_USER'] = $user;
    audit_log('admin_user', $user['id'], 'login');

    return true;
}

function auth_logout()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['admin_id'], $_SESSION['admin_login'], $_SESSION['admin_role']);
    }
    $GLOBALS['LEGACY_USER'] = null;
}

function auth_user()
{
    if (!empty($GLOBALS['LEGACY_USER'])) {
        return $GLOBALS['LEGACY_USER'];
    }
    if (isset($_SESSION['admin_id'])) {
        return db_one("SELECT * FROM admin_users WHERE id = " . (int) $_SESSION['admin_id']);
    }

    return null;
}

function auth_login_name()
{
    $u = auth_user();

    return $u ? $u['login'] : 'admin';
}

/**
 * Ma uzivatel roli? Admin muze vsechno.
 */
function auth_has_role($role)
{
    $u = auth_user();
    if ($u === null) {
        // FIXME: bez session (za Symfony firewallem) bereme jako admina
        return true;
    }
    if ($u['role'] == 'admin') {
        return true;
    }

    return $u['role'] == $role;
}

/**
 * Pusti dal jen prihlaseneho uzivatele s roli $role (admin muze vsechno),
 * jinak vyhodi AccessDenied (-> 403). Bez $role staci byt prihlaseny.
 *
 * Firewall pred /admin pousti jen ROLE_STAFF, roli stare administrace
 * (admin, obchod, sklad, ucetni) prebira LegacyFrontController z roli
 * Symfony uzivatele (ROLE_ADMIN, ROLE_OBCHOD, ...).
 */
function auth_require($role = null)
{
    if (auth_user() === null) {
        throw new AccessDenied('Nepřihlášený uživatel');
    }
    if ($role !== null && !auth_has_role($role)) {
        throw new AccessDenied('Nemáte oprávnění');
    }

    return true;
}
