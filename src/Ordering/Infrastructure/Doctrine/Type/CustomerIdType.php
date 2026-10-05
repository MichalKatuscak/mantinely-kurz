<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Doctrine\Type;

use App\Ordering\Domain\ValueObject\CustomerId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class CustomerIdType extends Type
{
    public const string NAME = 'customer_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CustomerId
    {
        if ($value === null || $value instanceof CustomerId) {
            return $value;
        }

        if (!is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string']);
        }

        return CustomerId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        if (!$value instanceof CustomerId) {
            throw InvalidType::new($value, self::NAME, ['null', CustomerId::class]);
        }

        return $value->value;
    }
}
