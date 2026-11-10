<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\ValueObject\Image;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Shared\Infrastructure\Persistence\PayloadNormalizer;

final class ImageCollectionNormalizer implements PayloadNormalizer
{
    public function __construct(
        private readonly ImageNormalizer $images = new ImageNormalizer(),
    ) {
    }

    public function supports(string $type): bool
    {
        return $type === ImageCollection::class;
    }

    public function export(object $value): array
    {
        if (!$value instanceof ImageCollection) {
            throw new \RuntimeException('Expected an image collection.');
        }

        return array_map(
            fn (Image $image): array => $this->images->export($image),
            $value->all(),
        );
    }

    public function import(string $type, mixed $value): ImageCollection
    {
        if (!is_array($value)) {
            throw new \RuntimeException('Expected an image collection payload.');
        }

        return new ImageCollection(...array_map(
            fn (mixed $image): Image => $this->images->import(Image::class, $image),
            $value,
        ));
    }
}
