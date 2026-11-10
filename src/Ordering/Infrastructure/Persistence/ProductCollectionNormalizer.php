<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Persistence;

use App\Ordering\Domain\ValueObject\Product;
use App\Ordering\Domain\ValueObject\ProductCollection;
use App\Shared\Infrastructure\Persistence\PayloadNormalizer;

final class ProductCollectionNormalizer implements PayloadNormalizer
{
    public function __construct(
        private readonly ProductNormalizer $products = new ProductNormalizer(),
    ) {
    }

    public function supports(string $type): bool
    {
        return $type === ProductCollection::class;
    }

    public function export(object $value): array
    {
        if (!$value instanceof ProductCollection) {
            throw new \RuntimeException('Expected a product collection.');
        }

        return array_map(
            fn (Product $product): array => $this->products->export($product),
            $value->getProducts(),
        );
    }

    public function import(string $type, mixed $value): ProductCollection
    {
        if (!is_array($value)) {
            throw new \RuntimeException('Expected a product collection payload.');
        }

        return new ProductCollection(array_map(
            fn (mixed $product): Product => $this->products->import(Product::class, $product),
            $value,
        ));
    }
}
