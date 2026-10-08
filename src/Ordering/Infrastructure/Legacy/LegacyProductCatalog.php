<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Legacy;

use App\Ordering\Application\Port\CatalogProduct;
use App\Ordering\Application\Port\ProductCatalog;
use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use Doctrine\DBAL\Connection;

/**
 * Protikorupční vrstva nad katalogem staré administrace (src/Legacy, tabulka products).
 * Překládá řádky legacy tabulky na CatalogProduct. Jediné místo v Orderingu, které
 * smí na src/Legacy (pravidlo LegacyAcl v deptrac.php).
 */
final readonly class LegacyProductCatalog implements ProductCatalog
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function find(ProductId $productId): ?CatalogProduct
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, name, price_cents, currency FROM products WHERE id = :id AND active = 1',
            ['id' => $productId->value],
        );

        return $row === false ? null : $this->toProduct($row);
    }

    public function all(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, price_cents, currency FROM products WHERE active = 1 ORDER BY name',
        );

        return array_map($this->toProduct(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private function toProduct(array $row): CatalogProduct
    {
        $id = $row['id'] ?? null;
        $name = $row['name'] ?? null;
        $priceCents = $row['price_cents'] ?? null;
        $currency = $row['currency'] ?? null;

        if (!is_string($id) || !is_string($name) || !is_numeric($priceCents) || !is_string($currency)) {
            throw new \UnexpectedValueException('Unexpected row in table products.');
        }

        return new CatalogProduct(
            ProductId::fromString($id),
            $name,
            new Money((int) $priceCents, Currency::from($currency)),
        );
    }
}
