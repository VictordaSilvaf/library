<?php

declare(strict_types=1);

namespace App\Ordering\Application\DTO;

use App\Ordering\Application\Command\CreateOrder;
use App\Ordering\Domain\ValueObject\Book;
use App\Ordering\Domain\ValueObject\BookCollection;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\Money;
use InvalidArgumentException;

class OrderInput
{
    /**
     * @param array<int, mixed> $books
     */
    private function __construct(
        private readonly string $customerId,
        private readonly array $books,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['customerId']) || !is_string($data['customerId']) || $data['customerId'] === '') {
            throw new InvalidArgumentException('Customer id is required');
        }

        if (!isset($data['books']) || !is_array($data['books'])) {
            throw new InvalidArgumentException('Books are required');
        }

        return new self($data['customerId'], $data['books']);
    }

    public function toCommand(): CreateOrder
    {
        $books = [];

        foreach ($this->books as $book) {
            if (!is_array($book)) {
                throw new InvalidArgumentException('Invalid book');
            }

            $books[] = new Book(
                self::stringField($book, 'title'),
                self::stringField($book, 'author'),
                Money::create(self::stringField($book, 'price'), self::stringField($book, 'currency'))
            );
        }

        return new CreateOrder(new CustomerId($this->customerId), new BookCollection($books));
    }

    /**
     * @param array<mixed> $book
     */
    private static function stringField(array $book, string $field): string
    {
        if (!isset($book[$field]) || !is_string($book[$field]) || $book[$field] === '') {
            throw new InvalidArgumentException(sprintf('Book %s is required', $field));
        }

        return $book[$field];
    }
}
