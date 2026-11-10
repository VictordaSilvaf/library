<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

interface PayloadNormalizer
{
    public function supports(string $type): bool;

    public function export(object $value): mixed;

    public function import(string $type, mixed $value): object;
}
