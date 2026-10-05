<?php

declare(strict_types=1);

namespace App\Ordering\Application\Port;

use App\Ordering\Domain\ValueObject\ProductId;

/**
 * Katalog zboží. Ceny a názvy spravuje stará administrace, Ordering je jen čte.
 */
interface ProductCatalog
{
    public function find(ProductId $productId): ?CatalogProduct;

    /** @return list<CatalogProduct> */
    public function all(): array;
}
