<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CategoryId;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Catalog\Domain\ValueObject\Sku;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

final class CreateCatalog
{
    private readonly ImageCollection $images;

    public function __construct(
        private readonly CatalogId $catalogId,
        private readonly Sku $sku,
        private readonly string $name,
        private readonly string $description,
        private readonly Money $price,
        private readonly CategoryId $categoryId,
        ImageCollection $images,
        private readonly DateTimeImmutable $occurredAt,
    ) {
        $this->images = $images->copy();
    }

    public function getCatalogId(): CatalogId
    {
        return $this->catalogId;
    }

    public function getSku(): Sku
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function getCategoryId(): CategoryId
    {
        return $this->categoryId;
    }

    public function getImages(): ImageCollection
    {
        return $this->images->copy();
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
