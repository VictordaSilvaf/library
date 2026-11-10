<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\ValueObject\CatalogId;
use DateTimeImmutable;

final class WithdrawCatalog
{
    public function __construct(
        private readonly CatalogId $catalogId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public function getCatalogId(): CatalogId
    {
        return $this->catalogId;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
