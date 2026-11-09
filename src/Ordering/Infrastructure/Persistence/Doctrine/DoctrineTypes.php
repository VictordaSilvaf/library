<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Types\Type;

final class DoctrineTypes
{
    public static function register(): void
    {
        self::add(OrderIdType::NAME, OrderIdType::class);
        self::add(CustomerIdType::NAME, CustomerIdType::class);
        self::add(ProductCollectionType::NAME, ProductCollectionType::class);
    }

    /**
     * @param class-string<Type> $class
     */
    private static function add(string $name, string $class): void
    {
        if (!Type::hasType($name)) {
            Type::addType($name, $class);
        }
    }
}
