<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Money;

final class MoneyNormalizer implements PayloadNormalizer
{
    public function supports(string $type): bool
    {
        return $type === Money::class;
    }

    public function export(object $value): array
    {
        if (!$value instanceof Money) {
            throw new \RuntimeException('Expected money.');
        }

        return ['amount' => $value->getAmount(), 'currency' => $value->getCurrency()];
    }

    public function import(string $type, mixed $value): Money
    {
        if (!is_array($value)) {
            throw new \RuntimeException('Expected a money payload.');
        }

        return Money::fromCents((int) $value['amount'], (string) $value['currency']);
    }
}
