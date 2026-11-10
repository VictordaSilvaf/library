<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObjects;

use App\Shared\Domain\ValueObject\Uuid;

enum CatalogStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';

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
}
