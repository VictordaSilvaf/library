<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Sku
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(trim($value));

        if ($value === '') {
            throw new InvalidArgumentException('O SKU não pode ser vazio.');
        }

        if (strlen($value) > 64) {
            throw new InvalidArgumentException(
                'O SKU deve ter no máximo 64 caracteres.'
            );
        }

        if (!preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $value)) {
            throw new InvalidArgumentException(
                'O SKU deve conter letras, números e hífens entre os segmentos.'
            );
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
