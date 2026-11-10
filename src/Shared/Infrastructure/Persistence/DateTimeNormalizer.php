<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeInterface;

final class DateTimeNormalizer implements PayloadNormalizer
{
    public function supports(string $type): bool
    {
        return $type === DateTimeImmutable::class;
    }

    public function export(object $value): string
    {
        if (!$value instanceof DateTimeImmutable) {
            throw new \RuntimeException('Expected a date.');
        }

        return $value->format(DateTimeInterface::ATOM);
    }

    public function import(string $type, mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable((string) $value);
    }
}
