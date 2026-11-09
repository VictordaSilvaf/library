<?php

namespace App\Ordering\Domain\ValueObject;


class BookCollection
{
    /**
     * @param Book[] $books
     */
    public function __construct(
        private array $books,
    ) {
    }

    public function getBooks(): array
    {
        return $this->books;
    }

    public function addBook(Book $book)
    {
        $this->books[] = $book;
    }

    public function removeBook(Book $book)
    {
        $this->books = array_filter($this->books, fn($b) => $b->title !== $book->title);
    }

    public function isEmpty(): bool
    {
        return count($this->books) === 0;
    }

    public function copy(): self
    {
        return new self($this->books);
    }
}
