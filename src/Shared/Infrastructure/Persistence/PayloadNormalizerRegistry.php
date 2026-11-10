<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use RuntimeException;

final class PayloadNormalizerRegistry
{
    /** @var list<PayloadNormalizer> */
    private array $normalizers = [];

    public function add(PayloadNormalizer $normalizer): self
    {
        $this->normalizers[] = $normalizer;

        return $this;
    }

    public function export(object $value): mixed
    {
        return $this->for($value::class)->export($value);
    }

    public function import(string $type, mixed $value): object
    {
        return $this->for($type)->import($type, $value);
    }

    private function for(string $type): PayloadNormalizer
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($type)) {
                return $normalizer;
            }
        }

        throw new RuntimeException('No payload normalizer supports ' . $type . '.');
    }
}
