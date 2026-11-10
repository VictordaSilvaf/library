<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\ValueObject\Image;
use App\Shared\Infrastructure\Persistence\PayloadNormalizer;

final class ImageNormalizer implements PayloadNormalizer
{
    public function supports(string $type): bool
    {
        return $type === Image::class;
    }

    public function export(object $value): array
    {
        if (!$value instanceof Image) {
            throw new \RuntimeException('Expected an image.');
        }

        return ['url' => $value->url];
    }

    public function import(string $type, mixed $value): Image
    {
        if (!is_array($value)) {
            throw new \RuntimeException('Expected an image payload.');
        }

        return new Image((string) $value['url']);
    }
}
