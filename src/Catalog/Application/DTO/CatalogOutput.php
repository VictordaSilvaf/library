<?php

declare(strict_types=1);

namespace App\Catalog\Application\DTO;

use App\Catalog\Domain\Aggregate\Catalog;
use App\Catalog\Domain\ValueObject\Image;
use JsonSerializable;

final class CatalogOutput implements JsonSerializable
{
    /**
     * @param list<string> $images
     */
    private function __construct(
        private readonly string $id,
        private readonly string $sku,
        private readonly string $name,
        private readonly string $description,
        private readonly int $amount,
        private readonly string $currency,
        private readonly string $categoryId,
        private readonly string $status,
        private readonly array $images,
    ) {
    }

    public static function fromCatalog(Catalog $catalog): self
    {
        return new self(
            $catalog->getId()->getValue(),
            $catalog->getSku()->value(),
            $catalog->getName(),
            $catalog->getDescription(),
            $catalog->getPrice()->getAmount(),
            $catalog->getPrice()->getCurrency(),
            $catalog->getCategoryId()->getValue(),
            $catalog->getStatus()->getValue(),
            array_map(static fn (Image $image): string => $image->url, $catalog->getImages()->all()),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => [
                'amount' => $this->amount,
                'currency' => $this->currency,
            ],
            'categoryId' => $this->categoryId,
            'status' => $this->status,
            'images' => $this->images,
        ];
    }
}
