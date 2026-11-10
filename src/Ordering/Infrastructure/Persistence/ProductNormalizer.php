<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Persistence;

use App\Ordering\Domain\ValueObject\Product;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Persistence\PayloadNormalizer;

final class ProductNormalizer implements PayloadNormalizer
{
    public function supports(string $type): bool
    {
        return $type === Product::class;
    }

    public function export(object $value): array
    {
        if (!$value instanceof Product) {
            throw new \RuntimeException('Expected a product.');
        }

        return [
            'title' => $value->getTitle(),
            'author' => $value->getAuthor(),
            'price' => [
                'amount' => $value->getPrice()->getAmount(),
                'currency' => $value->getPrice()->getCurrency(),
            ],
        ];
    }

    public function import(string $type, mixed $value): Product
    {
        if (!is_array($value)) {
            throw new \RuntimeException('Expected a product payload.');
        }

        return new Product(
            (string) $value['title'],
            (string) $value['author'],
            Money::fromCents((int) $value['price']['amount'], (string) $value['price']['currency']),
        );
    }
}
