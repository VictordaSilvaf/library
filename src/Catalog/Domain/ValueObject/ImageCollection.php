<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

class ImageCollection
{
    /** @var list<Image> */
    private array $items;

    public function __construct(Image ...$images)
    {
        $this->items = array_values($images);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function first(): Image
    {
        if ($this->items === []) {
            throw new \DomainException('Image collection is empty.');
        }

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
