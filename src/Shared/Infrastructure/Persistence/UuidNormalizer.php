<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;

final class UuidNormalizer implements PayloadNormalizer
{
    public function supports(string $type): bool
    {
        return is_a($type, Uuid::class, true);
    }

    public function export(object $value): string
    {
        if (!$value instanceof Uuid) {
            throw new \RuntimeException('Expected a uuid.');
        }

        return $value->getValue();
    }

    public function import(string $type, mixed $value): Uuid
    {
        if (!is_a($type, Uuid::class, true)) {
            throw new \RuntimeException('Expected a uuid type.');
        }

        return new $type((string) $value);
    }
}
