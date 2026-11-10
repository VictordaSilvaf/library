<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\ValueObject\Money;
use App\Ordering\Domain\ValueObject\Product;
use App\Ordering\Domain\ValueObject\ProductCollection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

final class ProductCollectionType extends Type
{
    public const NAME = 'product_collection';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ProductCollection
    {
        if ($value instanceof ProductCollection) {
            return $value;
        }

        if ($value === null || $value === '') {
            return new ProductCollection([]);
        }

        $decoded = is_array($value)
            ? $value
            : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Invalid product collection');
        }

        /** @var array<int, array{title: string, author: string, amount: int, currency: string}> $decoded */
        $products = [];

        foreach ($decoded as $row) {
            $products[] = new Product(
                $row['title'],
                $row['author'],
                Money::fromCents($row['amount'], $row['currency'])
            );
        }

        return new ProductCollection($products);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof ProductCollection) {
            throw new InvalidArgumentException('Expected a product collection');
        }

        $rows = [];

        foreach ($value->getProducts() as $product) {
            $rows[] = [
                'title' => $product->getTitle(),
                'author' => $product->getAuthor(),
                'amount' => $product->getPrice()->getAmount(),
                'currency' => $product->getPrice()->getCurrency(),
            ];
        }

        return json_encode($rows, JSON_THROW_ON_ERROR);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
