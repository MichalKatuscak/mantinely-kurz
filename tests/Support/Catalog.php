<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\Connection;

/**
 * Ukázkové zboží v katalogu staré administrace a na skladě.
 */
final class Catalog
{
    public const string KEYBOARD = '0192f0a0-2b00-7000-8000-00000000000a';
    public const string MOUSE = '0192f0a0-2b00-7000-8000-00000000000b';

    public static function seed(Connection $connection): void
    {
        foreach ([
            [self::KEYBOARD, 'Klávesnice', 50000, 10],
            [self::MOUSE, 'Myš', 25000, 10],
        ] as [$id, $name, $priceCents, $onHand]) {
            $connection->insert('products', [
                'id' => $id,
                'name' => $name,
                'price_cents' => $priceCents,
                'currency' => 'CZK',
                'active' => 1,
            ]);
            $connection->insert('stock_items', [
                'product_id' => $id,
                'on_hand' => $onHand,
                'reservations' => '[]',
            ]);
        }
    }
}
