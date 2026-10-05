<?php
/**
 * Puvodni funkce z roku 2014. Postupne nahrazovano helpers.php,
 * ale porad se to nekde pouziva, takze to tady zustava.
 */

/**
 * Formatovani ceny – STARA verze. Bere koruny (ne halere!) a vzdy pridava Kč.
 * @deprecated pouzivej format_price()
 */
function cena($kc)
{
    return number_format((float) $kc, 0, ',', '.') . ',- Kč';
}

/**
 * @deprecated pouzivej format_date()
 */
function datum($d)
{
    if (!$d) {
        return '';
    }

    return date('d.m.Y', strtotime((string) $d));
}

/**
 * Soucet objednavky v halerich, BEZ slevy, v mene objednavky.
 */
function order_total($orderId)
{
    global $db;
    legacy_db();
    $row = $db->one("SELECT SUM(quantity * unit_price_amount_in_cents) AS total FROM order_items WHERE order_id = '" . $orderId . "'");
    if ($row === null || $row['total'] === null) {
        return 0;
    }

    return (int) $row['total'];
}

/**
 * Soucet objednavky vcetne slevy. (Pridano 2017 kvuli fakturam.)
 */
function order_total_after_discount($orderId)
{
    global $db;
    legacy_db();
    $total = order_total($orderId);
    $o = $db->one("SELECT discount_amount_in_cents FROM orders WHERE id = '" . $orderId . "'");
    if ($o) {
        $total = $total - (int) $o['discount_amount_in_cents'];
    }
    if ($total < 0) {
        $total = 0;
    }

    return $total;
}

function customer_name($customerId)
{
    static $cache = array();
    if (isset($cache[$customerId])) {
        return $cache[$customerId];
    }
    global $db;
    legacy_db();
    $c = $db->one("SELECT name, email FROM customers WHERE id = '" . $customerId . "'");
    if ($c === null) {
        // zakaznik z noveho e-shopu, ktery jeste neni v customers
        $cache[$customerId] = '(neznámý zákazník)';
    } else {
        $cache[$customerId] = $c['name'] != '' ? $c['name'] : $c['email'];
    }

    return $cache[$customerId];
}

function product_name($productId)
{
    static $cache = array();
    if (isset($cache[$productId])) {
        return $cache[$productId];
    }
    global $db;
    legacy_db();
    $p = $db->one("SELECT name FROM products WHERE id = '" . $productId . "'");
    $cache[$productId] = $p ? $p['name'] : '(smazaný produkt)';

    return $cache[$productId];
}

/**
 * Zapis do auditniho logu.
 */
function audit_log($entity, $entityId, $action, $payload = array())
{
    global $db;
    legacy_db();
    $user = isset($_SESSION['admin_login']) ? $_SESSION['admin_login'] : 'system';
    $payload['user'] = $user;
    $db->exec("INSERT INTO audit_log (entity, entity_id, action, payload, created_at) VALUES ("
        . $db->quote($entity) . ", "
        . $db->quote($entityId) . ", "
        . $db->quote($action) . ", "
        . $db->quote(json_encode($payload)) . ", "
        . "'" . date('Y-m-d H:i:s') . "')");
}

function array_get($arr, $key, $default = null)
{
    return is_array($arr) && array_key_exists($key, $arr) ? $arr[$key] : $default;
}

/**
 * Prevod halere -> koruny. Pozor, vraci float.
 */
function hal2kc($cents)
{
    return $cents / 100;
}

function kc2hal($kc)
{
    // carka -> tecka, mezery pryc ("1 200,50" -> 1200.50)
    $kc = str_replace(array(' ', ','), array('', '.'), (string) $kc);

    return (int) round(((float) $kc) * 100);
}

/**
 * Mena objednavky.
 */
function order_currency($orderId)
{
    $o = db_one("SELECT currency FROM orders WHERE id = '" . $orderId . "'");

    return $o ? $o['currency'] : 'CZK';
}

/**
 * Pocet objednavek ve stavu. Pouziva dashboard.
 */
function count_orders($state = null)
{
    $sql = "SELECT COUNT(*) AS c FROM orders";
    if ($state !== null) {
        $sql .= " WHERE status = '" . $state . "'";
    }
    $r = db_one($sql);

    return $r ? (int) $r['c'] : 0;
}

/**
 * Stare overeni emailu.
 */
function is_email($email)
{
    return filter_var((string) $email, FILTER_VALIDATE_EMAIL) !== false;
}

// FIXME: nikde se nepouziva? (martin 2018)
function generate_password($len = 8)
{
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    $pw = '';
    for ($i = 0; $i < $len; $i++) {
        $pw .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $pw;
}

/**
 * Seznam ID z checkboxu -> kus SQL "'a','b','c'"
 * (escapuje jen apostrofy, viz db_escape_old)
 */
function ids_to_sql($ids)
{
    $out = array();
    foreach ((array) $ids as $id) {
        $out[] = "'" . db_escape_old($id) . "'";
    }

    return implode(',', $out);
}
