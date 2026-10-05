<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Catalog;

use App\Ordering\Application\Port\CatalogProduct;
use App\Ordering\Application\Port\ProductCatalog;
use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use Doctrine\DBAL\Connection;

/**
 * Čte tabulku products, kterou spravuje stará administrace (src/Legacy).
 */
final readonly class DbalProductCatalog implements ProductCatalog
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

        return array_values(array_map($this->toProduct(...), $rows));
    }

    /** @param array<string, mixed> $row */
    private function toProduct(array $row): CatalogProduct
    {
        return new CatalogProduct(
            ProductId::fromString((string) $row['id']),
            (string) $row['name'],
            new Money((int) $row['price_cents'], Currency::from((string) $row['currency'])),
        );
    }
}
