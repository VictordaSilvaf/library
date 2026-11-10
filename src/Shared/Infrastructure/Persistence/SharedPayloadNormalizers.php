<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

final class SharedPayloadNormalizers
{
    public static function registry(): PayloadNormalizerRegistry
    {
        return (new PayloadNormalizerRegistry())
            ->add(new DateTimeNormalizer())
            ->add(new EnumNormalizer())
            ->add(new MoneyNormalizer())
            ->add(new UuidNormalizer());
    }
}
