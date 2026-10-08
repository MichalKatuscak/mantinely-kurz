<?php

declare(strict_types=1);

namespace App\Tests\PHPStan;

use App\Tools\PHPStan\SqlConcatenationRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<SqlConcatenationRule>
 */
#[CoversClass(SqlConcatenationRule::class)]
final class SqlConcatenationRuleTest extends RuleTestCase
{
    private const string SELECT_ID = '"SELECT * FROM orders WHERE id = \'" . $id . "\'"';

    protected function getRule(): Rule
    {
        return new SqlConcatenationRule();
    }

    #[Test]
    public function reportsValuesConcatenatedIntoSql(): void
    {
        // Bezpečné dotazy v ukázce ($db->quote(), přetypování, literály, ids_to_sql(),
        // db_escape(), date() s pevným formátem, hodnoty s nativním typem čísla nebo
        // pevného řetězce) chybu nemají (část „✓“).
        $this->analyse([__DIR__.'/data/sql-concatenation.php'], [
            [self::message('skladane_sql()', self::SELECT_ID), 18],
            [self::message('skladane_sql()', '"UPDATE orders SET status = \'" . $newStatus . "\' WHERE id = \'" . $id . "\'"'), 20],
            [self::message('skladane_sql()', '"SELECT email FROM customers WHERE id = \'" . $order[\'customer_id\'] . "\'"'), 22],
            // Dlouhý dotaz: výňatek kolem první neošetřené části.
            [self::message('skladane_sql()', '…" WHERE product_id = \'" . $item[\'product_id\'] . "\'"', '"UPDATE stock_items SET reservations = " . $db->quote(json_encode($item)) . " WHERE product_id = \'" . $item[\'product_id\'] . "\'"'), 24],
            [self::message('skladane_sql()', '"SELECT COUNT(*) FROM orders WHERE status = \'" . $_GET[\'status\'] . "\'"'), 27],
            [self::message('skladane_sql()', '"SELECT product_id FROM order_items WHERE order_id = " . $id'), 29],
            [self::message('skladane_sql()', '"SELECT * FROM customers WHERE email = \'" . trim($id) . "\'"'), 31],
            [self::message('skladane_sql()', '"SELECT * FROM customers WHERE id = \'" . db_escape_old($id) . "\'"'), 34],
            [self::message('skladane_sql()', '"SELECT * FROM customers WHERE id = \'" . (string) $id . "\'"'), 36],
            [self::message('skladane_sql()', '"SELECT id FROM orders WHERE id IN (" . $idList . ")"'), 38],
            // Řetězec v uvozovkách s proměnnou: výňatek ukazuje proměnnou jako část.
            [self::message('skladane_sql()', self::SELECT_ID), 40],
            [self::message('skladane_sql()', '"SELECT * FROM orders WHERE id = \'" . $order[\'id\'] . "\'"'), 41],
            [self::message('skladane_sql()', '"SELECT * FROM orders ORDER BY " . ($id ? $newStatus : \'placed_at\')'), 43],
            [self::message('skladane_sql()', '"SELECT * FROM orders WHERE id = " . $order->quote($id)'), 45],
            [self::message('skladane_sql()', '"SELECT * FROM orders WHERE id = " . $id'), 47],
            [self::message('skladane_sql()', '"UPDATE orders SET placed_at = \'" . date($newStatus) . "\'"'), 49],
            [self::message('skladane_sql_pres_pomocne_funkce()', self::SELECT_ID), 55],
            [self::message('skladane_sql_pres_pomocne_funkce()', '"DELETE FROM order_notes WHERE id = \'" . $id . "\'"'), 56],
            [self::message('skladane_sql_pres_pomocne_funkce()', '"id = \'" . $id . "\'"'), 58],
            [self::message('skladane_sql_pres_typ()', self::SELECT_ID), 64],
            [self::message('LegacyOrderReport::radek()', self::SELECT_ID), 75],
            [self::message('skladane_sql_podle_typu()', '"SELECT * FROM orders WHERE id = " . $id'), 157],
            [self::message('skladane_sql_podle_typu()', '"SELECT * FROM orders WHERE note = \'" . $filter . "\'"'), 160],
            [self::message('skladane_sql_podle_typu()', '"SELECT * FROM admin_users WHERE token = \'" . md5($id, true) . "\'"'), 162],
            [self::message('skladane_sql_podle_typu()', '"SELECT * FROM admin_users WHERE token = \'" . sha1($id, $desc) . "\'"'), 163],
            [self::message('skladane_sql_podle_typu()', self::SELECT_ID), 165],
            // Nullsafe volání se hlásí jednou, i když ho PHPStan analyzuje dvakrát.
            [self::message('nullsafe()', self::SELECT_ID), 171],
            // Dotazy, které se liší až za neošetřenou částí, mají různé hlášky.
            [self::message('dva_stejne_dotazy()', '"SELECT * FROM orders WHERE customer_id = \'" . $customerId . "\' AND status = \'paid\' ORDER BY place…', '"SELECT * FROM orders WHERE customer_id = \'" . $customerId . "\' AND status = \'paid\' ORDER BY placed_at DESC LIMIT 10"'), 182],
            [self::message('dva_stejne_dotazy()', '"SELECT * FROM orders WHERE customer_id = \'" . $customerId . "\' AND status = \'cancelled\' ORDER BY …', '"SELECT * FROM orders WHERE customer_id = \'" . $customerId . "\' AND status = \'cancelled\' ORDER BY placed_at DESC LIMIT 10"'), 183],
        ]);
    }

    /**
     * Hláška obsahuje funkci, výňatek dotazu a otisk celého normalizovaného
     * dotazu ($full, když je výňatek zkrácený): každý výskyt má v baseline
     * vlastní položku a úprava starého dotazu kdekoli ho nahlásí znovu.
     */
    private static function message(string $where, string $excerpt, ?string $full = null): string
    {
        return sprintf(
            'Do not concatenate values into SQL in %s: %s (#%s); use $db->quote() or a cast.',
            $where,
            $excerpt,
            substr(sha1($full ?? $excerpt), 0, 8),
        );
    }
}
