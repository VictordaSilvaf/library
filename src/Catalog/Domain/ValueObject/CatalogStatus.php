<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

enum CatalogStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case WITHDRAWN = 'withdrawn';

    public function getValue(): string
    {
        return $this->value;
    }

    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    public function isPublished(): bool
    {
        return $this === self::PUBLISHED;
    }

    public function isWithdrawn(): bool
    {
        return $this === self::WITHDRAWN;
    }

    public function isTerminal(): bool
    {
        return $this === self::WITHDRAWN;
    }
}
