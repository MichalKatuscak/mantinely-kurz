<?php
/**
 * Odhlaseni.
 */

global $db;
legacy_db();

$u = auth_user();
if ($u) {
    audit_log('admin_user', $u['id'], 'logout');
}
auth_logout();

redirect(admin_url('login'));
