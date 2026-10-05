<?php
/**
 * Pripojeni k databazi – sdilene pres globalni promennou $db.
 *
 * Kazda stranka administrace dela:
 *
 *     global $db;
 *     legacy_db();
 *
 * a pak pouziva $db->query(...). Spojeni se vytvori az pri prvnim pouziti,
 * takze skript (nebo test) si muze predem nastavit $GLOBALS['LEGACY_DSN']
 * nebo rovnou $GLOBALS['db'].
 *
 * Puvodne MySQL (wedos), od 2024 bezi nad stejnou SQLite jako novy e-shop.
 */

use App\Legacy\lib\LegacyDb;

/**
 * Vrati DSN pro PDO.
 */
function legacy_dsn()
{
    if (!empty($GLOBALS['LEGACY_DSN'])) {
        return $GLOBALS['LEGACY_DSN'];
    }

    $url = getenv('DATABASE_URL');
    if ($url === false || $url === '') {
        $url = isset($_ENV['DATABASE_URL']) ? $_ENV['DATABASE_URL'] : (isset($_SERVER['DATABASE_URL']) ? $_SERVER['DATABASE_URL'] : '');
    }
    if ($url == '') {
        $url = 'sqlite:///%kernel.project_dir%/var/data_dev.db';
    }

    // src/Legacy/lib -> koren projektu
    $root = dirname(__DIR__, 3);
    $env = getenv('APP_ENV');
    if ($env === false || $env === '') {
        $env = isset($_SERVER['APP_ENV']) ? $_SERVER['APP_ENV'] : 'dev';
    }
    $url = str_replace('%kernel.project_dir%', $root, $url);
    $url = str_replace('%kernel.environment%', $env, $url);

    if (strpos($url, 'sqlite:///') === 0) {
        return 'sqlite:' . substr($url, strlen('sqlite:///'));
    }

    // MySQL – puvodni produkce, uz se nepouziva
    if (strpos($url, 'mysql://') === 0) {
        $p = parse_url($url);
        $dbname = isset($p['path']) ? ltrim($p['path'], '/') : 'eshop';
        $GLOBALS['LEGACY_DB_USER'] = isset($p['user']) ? $p['user'] : 'root';
        $GLOBALS['LEGACY_DB_PASS'] = isset($p['pass']) ? $p['pass'] : '';

        return 'mysql:host=' . (isset($p['host']) ? $p['host'] : 'localhost') . ';dbname=' . $dbname . ';charset=utf8';
    }

    return $url;
}

/**
 * Lazy pripojeni. Naplni $GLOBALS['db'].
 */
function legacy_db()
{
    if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof LegacyDb) {
        return $GLOBALS['db'];
    }

    $dsn = legacy_dsn();
    $user = isset($GLOBALS['LEGACY_DB_USER']) ? $GLOBALS['LEGACY_DB_USER'] : null;
    $pass = isset($GLOBALS['LEGACY_DB_PASS']) ? $GLOBALS['LEGACY_DB_PASS'] : null;

    $GLOBALS['db'] = new LegacyDb($dsn, $user, $pass);

    return $GLOBALS['db'];
}

function db_query($sql)
{
    global $db;
    legacy_db();

    return $db->query($sql);
}

function db_one($sql)
{
    global $db;
    legacy_db();

    return $db->one($sql);
}

function db_value($sql)
{
    global $db;
    legacy_db();

    return $db->value($sql);
}

function db_exec($sql)
{
    global $db;
    legacy_db();

    return $db->exec($sql);
}

/**
 * Escapuje hodnotu VCETNE uvozovek (pozor, driv bez – viz db_escape_old).
 */
function db_escape($value)
{
    global $db;
    legacy_db();

    return $db->quote($value);
}

// stara verze z mysql_real_escape_string doby – vraci BEZ uvozovek
// FIXME: pouziva se jeste v customer_edit.php, sjednotit
function db_escape_old($value)
{
    return str_replace(array("\\", "'"), array("\\\\", "''"), (string) $value);
}

function db_last_id()
{
    global $db;
    legacy_db();

    return $db->lastId();
}

/**
 * Sestavi INSERT z pole. Hodnoty escapuje.
 */
function db_insert($table, $data)
{
    $cols = array();
    $vals = array();
    foreach ($data as $k => $v) {
        $cols[] = $k;
        $vals[] = db_escape($v);
    }
    db_exec('INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')');

    return db_last_id();
}

/**
 * Sestavi UPDATE z pole. $where je hotovy kus SQL (!)
 */
function db_update($table, $data, $where)
{
    $sets = array();
    foreach ($data as $k => $v) {
        $sets[] = $k . ' = ' . db_escape($v);
    }

    return db_exec('UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where);
}

/**
 * Generator UUID v4 – produkty a zakaznici maji od 2024 UUID kvuli novemu e-shopu.
 */
function db_uuid()
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Datum pro DB. MySQL i SQLite snesou stejny format.
 */
function db_now()
{
    return date('Y-m-d H:i:s');
}
