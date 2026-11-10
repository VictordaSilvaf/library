<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use BackedEnum;

final class EnumNormalizer implements PayloadNormalizer
{
    public function supports(string $type): bool
    {
        return enum_exists($type) && is_a($type, BackedEnum::class, true);
    }

    public function export(object $value): string|int
    {
        if (!$value instanceof BackedEnum) {
            throw new \RuntimeException('Expected an enum.');
        }

        return $value->value;
    }

    public function import(string $type, mixed $value): BackedEnum
    {
        if (!is_a($type, BackedEnum::class, true)) {
            throw new \RuntimeException('Expected an enum type.');
        }

        return $type::from($value);
    }
}
