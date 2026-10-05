<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Doctrine\Type;

use App\Ordering\Domain\ValueObject\OrderId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class OrderIdType extends Type
{
    public const string NAME = 'order_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?OrderId
    {
        if ($value === null || $value instanceof OrderId) {
            return $value;
        }

        if (!is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string']);
        }

        return OrderId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        if (!$value instanceof OrderId) {
            throw InvalidType::new($value, self::NAME, ['null', OrderId::class]);
        }

        return $value->value;
    }
}
