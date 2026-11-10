<?php

declare(strict_types=1);

namespace Src\Catalog\Domain\ValueObjects;

use InvalidArgumentException;
use Ramsey\Collection\Collection;

class ImageCollection extends Collection
{
    /** @var list<Image> */
    public array $items;

    public function __construct(Image ...$images)
    {
        if (count($images) === 0) {
            throw new InvalidArgumentException(
                'At least one image is required.'
            );
        }

        $this->items = array_values($images);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function first(): Image
    {
        return $this->items[0];
    }

    /** @return list<Image> */
    public function all(): array
    {
        return $this->items;
    }

    public function copy(): self
    {
        return new self(...$this->items);
    }
}
