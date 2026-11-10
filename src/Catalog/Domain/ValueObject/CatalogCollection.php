<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Catalog;
use Ramsey\Collection\Collection;
use InvalidArgumentException;

class CatalogCollection extends Collection
{
    /** @var list<Catalog> */
    public array $items;

    public function __construct(Catalog ...$catalogs)
    {
        if (count($catalogs) === 0) {
            throw new InvalidArgumentException(
                'At least one catalog is required.'
            );
        }

        $this->items = array_values($catalogs);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function first(): Catalog
    {
        return $this->items[0];
    }

    public function all(): array
    {
        return $this->items;
    }
}
