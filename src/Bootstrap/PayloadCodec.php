<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Catalog\Infrastructure\Persistence\ImageCollectionNormalizer;
use App\Catalog\Infrastructure\Persistence\ImageNormalizer;
use App\Catalog\Infrastructure\Persistence\SkuNormalizer;
use App\Ordering\Infrastructure\Persistence\ProductCollectionNormalizer;
use App\Ordering\Infrastructure\Persistence\ProductNormalizer;
use App\Shared\Infrastructure\Persistence\DomainEventSerializer;
use App\Shared\Infrastructure\Persistence\SharedPayloadNormalizers;

final class PayloadCodec
{
    public static function serializer(): DomainEventSerializer
    {
        return new DomainEventSerializer(
            SharedPayloadNormalizers::registry()
                ->add(new ProductCollectionNormalizer())
                ->add(new ProductNormalizer())
                ->add(new ImageCollectionNormalizer())
                ->add(new ImageNormalizer())
                ->add(new SkuNormalizer()),
        );
    }
}
