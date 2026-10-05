<?php
/**
 * Uzivatele administrace (novejsi verze users.php, nedokonceno – martin 2017).
 */

namespace App\Legacy\Admin;

class UserController extends BaseController
{
    protected $title = 'Uživatelé';

    public function listAction()
    {
        global $db;
        legacy_db();
        $users = $db->query("SELECT id, login, role, last_login FROM admin_users ORDER BY login");

        return $this->renderLayout('users/list', array('users' => $users, 'roles' => config('roles', array())));
    }

    public function saveAction()
    {
        global $db;
        legacy_db();

        $id = (int) post_param('id', 0);
        $login = post_param('login');
        $role = post_param('role', 'obchod');
        $password = post_param('password');

        if ($login == '') {
            flash('Chybí login', 'error');

            return $this->redirect(admin_url('users'));
        }

        if ($id > 0) {
            $sql = "UPDATE admin_users SET login = '" . $login . "', role = '" . $role . "'";
            if ($password != '') {
                $sql .= ", password_md5 = '" . md5($password) . "'";
            }
            $sql .= " WHERE id = " . $id;
            $db->exec($sql);
        } else {
            if ($password == '') {
                $password = generate_password();
                flash('Vygenerované heslo: ' . $password);
            }
            $db->exec("INSERT INTO admin_users (login, password_md5, role, last_login) VALUES ('" . $login . "', '" . md5($password) . "', '" . $role . "', NULL)");
            $id = (int) $db->lastId();
        }
        audit_log('admin_user', $id, 'save', array('login' => $login, 'role' => $role));
        flash('Uloženo');

        return $this->redirect(admin_url('users'));
    }

    public function deleteAction()
    {
        global $db;
        legacy_db();
        $id = (int) post_param('id', 0);
        // nesmi smazat sam sebe
        $me = auth_user();
        if ($me && (int) $me['id'] == $id) {
            flash('Nemůžete smazat sami sebe', 'error');

            return $this->redirect(admin_url('users'));
        }
        $db->exec("DELETE FROM admin_users WHERE id = " . $id);
        audit_log('admin_user', $id, 'delete');

        return $this->redirect(admin_url('users'));
    }
}
