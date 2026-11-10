<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\ValueObject\Sku;
use App\Shared\Infrastructure\Persistence\PayloadNormalizer;

final class SkuNormalizer implements PayloadNormalizer
{
    public function supports(string $type): bool
    {
        return $type === Sku::class;
    }

    public function export(object $value): string
    {
        if (!$value instanceof Sku) {
            throw new \RuntimeException('Expected a sku.');
        }

        return $value->value();
    }

    public function import(string $type, mixed $value): Sku
    {
        return new Sku((string) $value);
    }
}
