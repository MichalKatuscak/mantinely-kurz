<?php

declare(strict_types=1);

// Ukázky pro LegacyTableWriteRule ve stylu staré administrace: bez typů, global $db.
// Hlášené případy (✕) pocházejí z běhů agenta v měření stará administrace
// (video/zaznamy/mereni-legacy*/diff.patch v repozitáři kurzu).

namespace App\Tests\PHPStan\Data;

use App\Legacy\lib\LegacyDb;

function storno_primo_v_sql($id, $data)
{
    global $db;

    // ✕ stav objednávky přímo v SQL (r4-sonnet, r6-sonnet, r7-opus)
    $db->exec("UPDATE orders SET status = 'cancelled' WHERE id = " . $db->quote($id));
    // ✕ rezervace přímo v SQL (r4-sonnet, r7-haiku, r9-haiku)
    $db->exec("UPDATE stock_items SET reservations = " . $db->quote(json_encode($data))
        . " WHERE product_id = " . $db->quote($id));
    // ✕ zápis i přes query(), malá písmena, mezery na začátku
    $db->query("  insert into stock_items (product_id, on_hand) VALUES (" . $db->quote($id) . ", 0)");
    // ✕ DELETE v apostrofech
    $db->exec('DELETE FROM orders WHERE id = ' . $db->quote($id));
    // ✕ víceřádkový dotaz: hlásí se řádek volání
    $db->exec("
        UPDATE orders SET status = 'paid'
        WHERE id = " . $db->quote($id));
    // ✕ dotaz bez skládání
    $db->exec("DELETE FROM stock_items WHERE on_hand = 0");
    // ✕ dotaz s proměnnou uvnitř řetězce
    $db->exec("UPDATE orders SET placed_at = '{$data}' WHERE id = 1");
    // ✕ zápis přes one()/value() je pořád zápis
    $db->one("UPDATE orders SET status = 'paid' WHERE id = " . $db->quote($id));
    // ✕ globální proměnná
    $GLOBALS['db']->exec("UPDATE orders SET status = 'paid'");
    // ✕ databáze z legacy_db()
    legacy_db()->exec("UPDATE stock_items SET reservations = '{}'");
}

function zapis_pres_pomocne_funkce($id)
{
    // ✕ pomocné funkce z src/Legacy/lib/db.php
    db_exec("UPDATE orders SET status = 'cancelled' WHERE id = " . db_escape($id));
    // ✕ db_update()/db_insert() s tabulkou v prvním argumentu
    db_update('stock_items', array('on_hand' => 0), 'product_id = ' . db_escape($id));
    \db_insert('orders', array('id' => $id));
}

function zapis_pres_typ(LegacyDb $conn)
{
    // ✕ objekt databáze podle typu, i když se proměnná nejmenuje $db
    $conn->exec('DELETE FROM orders');
}

final class LegacyStockService
{
    /** @var LegacyDb */
    private $db;

    public function nulovat($id): void
    {
        // ✕ $this->db
        $this->db->exec('UPDATE stock_items SET on_hand = 0 WHERE product_id = ' . $this->db->quote($id));
    }
}

function povolene($id, $mailer, $table)
{
    global $db;

    // ✓ čtení
    $db->query('SELECT * FROM orders WHERE id = ' . $db->quote($id));
    $db->one('SELECT reservations FROM stock_items WHERE product_id = ' . $db->quote($id));
    // ✓ zápis do jiných tabulek
    $db->exec("INSERT INTO order_notes (order_id, note) VALUES (" . $db->quote($id) . ", 'storno')");
    $db->exec('UPDATE order_items SET quantity = 1 WHERE id = ' . $db->quote($id));
    $db->exec("UPDATE orders_archive SET status = 'cancelled'");
    $db->exec('DELETE FROM stock_items_log');
    // ✓ tabulka orders jen v podmínce
    $db->exec("DELETE FROM order_notes WHERE order_id IN (SELECT id FROM orders WHERE status = 'cancelled')");
    // ✓ tabulka není v řetězci (pravidlo ji nezná)
    $db->exec('UPDATE ' . $table . " SET status = 'paid'");
    // ✓ jiný objekt než databáze
    $mailer->query("UPDATE orders SET status = 'paid'");
    // ✓ jiná metoda databáze
    $db->quote("UPDATE orders SET status = 'paid'");
    // ✓ jiné pomocné funkce a jiná tabulka v db_update()
    db_update('customers', array('name' => 'x'), 'id = ' . db_escape($id));
    log_line("UPDATE orders SET status = 'paid'");
    // $db->exec("UPDATE orders SET status = 'paid'"); ✓ komentář
}

function nullsafe($db)
{
    // ✕ nullsafe volání
    $db?->exec('DELETE FROM orders WHERE id = 1');
    // ✓ nullsafe čtení
    $db?->query('SELECT * FROM orders');
}
