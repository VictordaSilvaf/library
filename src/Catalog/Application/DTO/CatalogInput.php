<?php

declare(strict_types=1);

namespace App\Catalog\Application\DTO;

use App\Catalog\Application\Command\CreateCatalog;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CategoryId;
use App\Catalog\Domain\ValueObject\Image;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Catalog\Domain\ValueObject\Sku;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final class CatalogInput
{
    /**
     * @param list<mixed> $images
     */
    private function __construct(
        private readonly string $sku,
        private readonly string $name,
        private readonly string $description,
        private readonly string $price,
        private readonly string $currency,
        private readonly string $categoryId,
        private readonly array $images,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::stringField($data, 'sku'),
            self::stringField($data, 'name'),
            self::stringField($data, 'description'),
            self::stringField($data, 'price'),
            self::stringField($data, 'currency'),
            self::stringField($data, 'categoryId'),
            self::images($data),
        );
    }

    public function toCommand(DateTimeImmutable $occurredAt): CreateCatalog
    {
        $images = [];

        foreach ($this->images as $image) {
            if (!is_array($image) || !isset($image['url']) || !is_string($image['url']) || $image['url'] === '') {
                throw new InvalidArgumentException('Image url is required');
            }

            $images[] = new Image($image['url']);
        }

        return new CreateCatalog(
            CatalogId::random(),
            new Sku($this->sku),
            $this->name,
            $this->description,
            Money::create($this->price, $this->currency),
            new CategoryId($this->categoryId),
            new ImageCollection(...$images),
            $occurredAt,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function stringField(array $data, string $field): string
    {
        if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') {
            throw new InvalidArgumentException(sprintf('Catalog %s is required', $field));
        }

        return $data[$field];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<mixed>
     */
    private static function images(array $data): array
    {
        if (!isset($data['images']) || !is_array($data['images'])) {
            throw new InvalidArgumentException('Catalog images are required');
        }

        return array_values($data['images']);
    }
}
