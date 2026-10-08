<?php

declare(strict_types=1);

namespace App\Tests\PHPStan;

use App\Tools\PHPStan\LegacyTableWriteRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<LegacyTableWriteRule>
 */
#[CoversClass(LegacyTableWriteRule::class)]
final class LegacyTableWriteRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LegacyTableWriteRule();
    }

    #[Test]
    public function reportsWritesToOrdersAndStockItemsOnly(): void
    {
        // Řádky bez chyby v ukázce jsou čtení, jiné tabulky a jiné objekty (část „✓“).
        $this->analyse([__DIR__.'/data/legacy-table-write.php'], [
            [self::message('orders', 'zapis_primo_v_sql()', '"UPDATE orders SET status = \'cancelled\' WHERE id = " . $db->quote($id)'), 18],
            [self::message('stock_items', 'zapis_primo_v_sql()', '"UPDATE stock_items SET reservations = " . $db->quote(json_encode($data)) . " WHERE product_id = " .…', '"UPDATE stock_items SET reservations = " . $db->quote(json_encode($data)) . " WHERE product_id = " . $db->quote($id)'), 20],
            [self::message('stock_items', 'zapis_primo_v_sql()', '" insert into stock_items (product_id, on_hand) VALUES (" . $db->quote($id) . ", 0)"'), 23],
            [self::message('orders', 'zapis_primo_v_sql()', '"DELETE FROM orders WHERE id = " . $db->quote($id)'), 25],
            // Víceřádkový dotaz: mezery a konce řádků slité do jedné mezery, takže
            // otisk vyjde stejně v checkoutu s CRLF i s LF.
            [self::message('orders', 'zapis_primo_v_sql()', '" UPDATE orders SET status = \'paid\' WHERE id = " . $db->quote($id)'), 27],
            [self::message('stock_items', 'zapis_primo_v_sql()', '"DELETE FROM stock_items WHERE on_hand = 0"'), 31],
            [self::message('orders', 'zapis_primo_v_sql()', '"UPDATE orders SET placed_at = \'" . $data . "\' WHERE id = 1"'), 33],
            [self::message('orders', 'zapis_primo_v_sql()', '"UPDATE orders SET status = \'paid\' WHERE id = " . $db->quote($id)'), 35],
            [self::message('orders', 'zapis_primo_v_sql()', '"UPDATE orders SET status = \'paid\'"'), 37],
            [self::message('stock_items', 'zapis_primo_v_sql()', '"UPDATE stock_items SET reservations = \'{}\'"'), 39],
            [self::message('orders', 'zapis_pres_pomocne_funkce()', '"UPDATE orders SET status = \'cancelled\' WHERE id = " . db_escape($id)'), 45],
            // Pomocné funkce: otisk ze všech argumentů.
            [self::message('stock_items', 'zapis_pres_pomocne_funkce()', 'db_update(\'stock_items\', …)', '"stock_items" . array(\'on_hand\' => 0) . (\'product_id = \' . db_escape($id))'), 47],
            [self::message('orders', 'zapis_pres_pomocne_funkce()', 'db_insert(\'orders\', …)', '"orders" . array(\'id\' => $id)'), 48],
            [self::message('orders', 'zapis_pres_typ()', '"DELETE FROM orders"'), 54],
            [self::message('stock_items', 'LegacyStockService::nulovat()', '"UPDATE stock_items SET on_hand = 0 WHERE product_id = " . $this->db->quote($id)'), 65],
            // Nullsafe volání se hlásí jednou, i když ho PHPStan analyzuje dvakrát.
            [self::message('orders', 'nullsafe()', '"DELETE FROM orders WHERE id = 1"'), 98],
        ]);
    }

    /**
     * Hláška obsahuje funkci, výňatek dotazu a otisk celého normalizovaného
     * dotazu ($full, když je výňatek zkrácený): každý výskyt má v baseline
     * vlastní položku a úprava starého dotazu kdekoli ho nahlásí znovu.
     */
    private static function message(string $table, string $where, string $excerpt, ?string $full = null): string
    {
        return sprintf(
            'Do not write table %s from legacy code in %s: %s (#%s); use the domain (aggregate methods, commands and events) instead.',
            $table,
            $where,
            $excerpt,
            substr(sha1($full ?? $excerpt), 0, 8),
        );
    }
}
