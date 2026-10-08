<?php

declare(strict_types=1);

// Ukázky pro SqlConcatenationRule ve stylu staré administrace: bez typů, global $db.
// Hlášené případy (✕) jsou vzory, které pravidlo hlásit má, i s obměnami;
// u povolených (✓) je podobný kód, který hlásit nemá.

namespace App\Tests\PHPStan\Data;

use App\Legacy\lib\LegacyDb;

function skladane_sql($id, $newStatus, $order, $item, $ids, $idList)
{
    global $db;

    // ✕ hodnota v apostrofech
    $db->one("SELECT * FROM orders WHERE id = '" . $id . "'");
    // ✕ dvě hodnoty v jednom dotazu: jedna chyba na volání
    $db->exec("UPDATE orders SET status = '" . $newStatus . "' WHERE id = '" . $id . "'");
    // ✕ prvek pole
    $db->one("SELECT email FROM customers WHERE id = '" . $order['customer_id'] . "'");
    // ✕ jedna část ošetřená, druhá ne
    $db->exec("UPDATE stock_items SET reservations = " . $db->quote(json_encode($item))
        . " WHERE product_id = '" . $item['product_id'] . "'");
    // ✕ vstup z požadavku
    $db->value("SELECT COUNT(*) FROM orders WHERE status = '" . $_GET['status'] . "'");
    // ✕ query()
    $db->query('SELECT product_id FROM order_items WHERE order_id = ' . $id);
    // ✕ funkce, která hodnotu neošetří
    $db->query("SELECT * FROM customers WHERE email = '" . trim($id) . "'");
    // ✕ db_escape_old() (vrací hodnotu BEZ apostrofů) hlášeno konvencí: bezpečnost závisí
    //   na okolních apostrofech, které volání neukazuje
    $db->query("SELECT * FROM customers WHERE id = '" . db_escape_old($id) . "'");
    // ✕ přetypování na řetězec nic neošetří
    $db->query("SELECT * FROM customers WHERE id = '" . (string) $id . "'");
    // ✕ proměnná: pravidlo nevidí, odkud hodnota přišla, ani když z ids_to_sql()
    $db->query('SELECT id FROM orders WHERE id IN (' . $idList . ')');
    // ✕ hodnota uvnitř řetězce v uvozovkách
    $db->query("SELECT * FROM orders WHERE id = '$id'");
    $db->query("SELECT * FROM orders WHERE id = '{$order['id']}'");
    // ✕ podmínka, jejíž jedna větev je hodnota zvenku
    $db->query('SELECT * FROM orders ORDER BY ' . ($id ? $newStatus : 'placed_at'));
    // ✕ quote() jiného objektu než databáze
    $db->query('SELECT * FROM orders WHERE id = ' . $order->quote($id));
    // ✕ globální proměnná
    $GLOBALS['db']->query('SELECT * FROM orders WHERE id = ' . $id);
    // ✕ date() s formátem zvenku vrátí neznámé znaky beze změny (i apostrof)
    $db->exec("UPDATE orders SET placed_at = '" . date($newStatus) . "'");
}

function skladane_sql_pres_pomocne_funkce($id)
{
    // ✕ pomocné funkce z src/Legacy/lib/db.php
    db_query("SELECT * FROM orders WHERE id = '" . $id . "'");
    db_exec("DELETE FROM order_notes WHERE id = '" . $id . "'");
    // ✕ db_update(): podmínka (třetí argument) je hotový kus SQL
    db_update('customers', array('name' => $id), "id = '" . $id . "'");
}

function skladane_sql_pres_typ(LegacyDb $conn, $id)
{
    // ✕ objekt databáze podle typu
    $conn->query("SELECT * FROM orders WHERE id = '" . $id . "'");
}

final class LegacyOrderReport
{
    /** @var LegacyDb */
    private $db;

    public function radek($id): mixed
    {
        // ✕ $this->db
        return $this->db->one("SELECT * FROM orders WHERE id = '" . $id . "'");
    }

    public function bezpecny($id): mixed
    {
        // ✓ $this->db->quote()
        return $this->db->one('SELECT * FROM orders WHERE id = ' . $this->db->quote($id));
    }
}

function bezpecne_sql($id, $qty, $rate, $ids, $desc, $sql, $cache, LegacyDb $conn)
{
    global $db;

    // ✓ $db->quote()
    $db->one('SELECT * FROM orders WHERE id = ' . $db->quote($id));
    $db->exec("INSERT INTO order_notes (order_id, author, note) VALUES ("
        . $db->quote($id) . ', ' . $db->quote('system') . ', '
        . $db->quote('Storno: ' . $id) . ')');
    $conn->one('SELECT * FROM orders WHERE id = ' . $conn->quote($id));
    $GLOBALS['db']->one('SELECT * FROM orders WHERE id = ' . $GLOBALS['db']->quote($id));
    // ✓ přetypování na číslo
    $db->exec('UPDATE stock_items SET on_hand = ' . (int) $qty . ' WHERE product_id = ' . $db->quote($id));
    $db->exec('UPDATE exchange_rates SET rate_to_czk = ' . (float) $rate . " WHERE currency = 'EUR'");
    $db->query('SELECT * FROM orders LIMIT ' . intval($qty) . ' OFFSET ' . floatval($rate));
    // ✓ literály
    $db->query('SELECT * FROM orders' . " WHERE status = 'paid'" . ' LIMIT ' . 10 . ' OFFSET ' . 0.0);
    // ✓ ids_to_sql(): každé ID v apostrofech, apostrofy zdvojené (ProductController)
    $db->exec("UPDATE products SET active = 0 WHERE id IN (" . ids_to_sql($ids) . ")");
    // ✓ datum s pevným formátem, db_now(), otisk md5()/sha1()
    $db->exec("INSERT INTO order_notes (order_id, note, created_at) VALUES ("
        . $db->quote($id) . ", 'storno', '"
        . date('Y-m-d H:i:s') . "')");
    $db->exec("UPDATE orders SET placed_at = '" . db_now() . "' WHERE id = " . $db->quote($id));
    $db->one("SELECT * FROM admin_users WHERE password_md5 = '" . md5($id) . "' OR token = '" . sha1($id) . "'");
    // ✓ db_escape(): obal nad $db->quote()
    $db->query('SELECT * FROM customers WHERE id = ' . db_escape($id));
    // ✓ podmínka, jejíž obě větve jsou bezpečné
    $db->query('SELECT * FROM orders ORDER BY placed_at ' . ($desc ? 'DESC' : 'ASC'));
    $db->query('SELECT * FROM orders WHERE id = ' . ($id ? $db->quote($id) : 'NULL'));
    // ✓ hodnota uvnitř řetězce přes quote()
    $db->query("SELECT * FROM orders WHERE id = {$db->quote($id)}");
    // ✓ dotaz bez skládání (proměnnou $sql pravidlo nekontroluje)
    $db->query("SELECT * FROM orders WHERE status = 'paid'");
    $db->query($sql);
    // ✓ jiný objekt než databáze
    $cache->query('dashboard_' . $id);
    // ✓ jiná metoda databáze
    $db->quote('x' . $id);
    // ✓ pomocné funkce s ošetřenou hodnotou
    db_query('SELECT * FROM orders WHERE id = ' . db_escape($id));
    db_update('customers', array('name' => $id), 'id = ' . db_escape($id));
    // ✓ md5() s výslovným false (šestnáctkový výstup)
    $db->one("SELECT * FROM admin_users WHERE password_md5 = '" . md5($id, false) . "'");
    // ✓ databáze z legacy_db()
    legacy_db()->query('SELECT * FROM orders WHERE id = ' . legacy_db()->quote($id));
}

function bezpecne_podle_typu($id, $rows, $desc, int $page)
{
    global $db;

    // ✓ hodnota, kterou PHPStan zná jako číslo (vzor users.php: (int) get_param('id'))
    $uid = (int) get_param('id');
    $db->exec('DELETE FROM admin_users WHERE id = ' . $uid);
    // ✓ aritmetika s čísly, count(), parametr typu int, číselná konstanta
    $db->exec('UPDATE products SET price_cents = ' . ($uid * 100 + 1) . ' WHERE id = ' . $db->quote($id));
    $db->query('SELECT * FROM orders LIMIT ' . count($rows) . ' OFFSET ' . $page);
    $db->query('SELECT * FROM orders LIMIT ' . PHP_INT_MAX);
    // ✓ proměnná s pevnými řetězci bez apostrofů a zpětných lomítek
    $dir = $desc ? 'ASC' : 'DESC';
    $db->query('SELECT * FROM orders ORDER BY placed_at ' . $dir);
}

/**
 * @param int $id
 */
function skladane_sql_podle_typu($id, $desc)
{
    global $db;

    // ✕ typ jen z phpDoc: pravidlo věří jen nativním typům, phpDoc může lhát
    $db->query('SELECT * FROM orders WHERE id = ' . $id);
    // ✕ pevný řetězec s apostrofem
    $filter = $desc ? "status = 'paid'" : "x' OR '1'='1";
    $db->query("SELECT * FROM orders WHERE note = '" . $filter . "'");
    // ✕ md5()/sha1() s binárním výstupem může obsahovat apostrof
    $db->one("SELECT * FROM admin_users WHERE token = '" . md5($id, true) . "'");
    $db->one("SELECT * FROM admin_users WHERE token = '" . sha1($id, $desc) . "'");
    // ✕ databáze z legacy_db()
    legacy_db()->query("SELECT * FROM orders WHERE id = '" . $id . "'");
}

function nullsafe($id, $db)
{
    // ✕ nullsafe volání
    $db?->query("SELECT * FROM orders WHERE id = '" . $id . "'");
    // ✓ nullsafe volání s quote()
    $db?->query('SELECT * FROM orders WHERE id = ' . $db?->quote($id));
}

function dva_stejne_dotazy($id, $customerId)
{
    global $db;

    // ✕ výňatek v hlášce je kolem první neošetřené části: dva dotazy, které
    //   se liší jen jinde, mají v baseline každý svou položku
    $db->query("SELECT * FROM orders WHERE customer_id = '" . $customerId . "' AND status = 'paid' ORDER BY placed_at DESC LIMIT 10");
    $db->query("SELECT * FROM orders WHERE customer_id = '" . $customerId . "' AND status = 'cancelled' ORDER BY placed_at DESC LIMIT 10");
}
