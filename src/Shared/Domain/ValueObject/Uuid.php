<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Ramsey\Uuid\Uuid as RamseyUuid;

abstract class Uuid
{
    public function __construct(private readonly string $value)
    {
        self::validate($value);
    }

    public static function random(): static
    {
        return new static(RamseyUuid::uuid4()->toString());
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public static function validate(string $value): bool
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid UUID format');
        }

        return true;
    }
}
