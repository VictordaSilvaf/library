<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

enum CatalogStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function getValue(): string
    {
        return $this->value;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this === self::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function isTerminal(): bool
    {
        return $this === self::CANCELLED;
    }
}
