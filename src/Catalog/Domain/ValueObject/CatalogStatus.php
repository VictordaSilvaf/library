<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;

enum CatalogStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';
    case DELETED = 'deleted';
    case CANCELLED = 'cancelled';
    case CONFIRMED = 'confirmed';
    case PENDING = 'pending';

    public function getValue(): string
    {
        return $this->value;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this === self::INACTIVE;
    }

    public function isArchived(): bool
    {
        return $this === self::ARCHIVED;
    }

    public function isDeleted(): bool
    {
        return $this === self::DELETED;
    }

    public function isConfirmed(): bool
    {
        return $this === self::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isTerminal(): bool
    {
        return $this === self::CANCELLED
            || $this === self::ARCHIVED
            || $this === self::DELETED;
    }
}
