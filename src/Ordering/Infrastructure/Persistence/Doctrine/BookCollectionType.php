<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Persistence\Doctrine;

use App\Ordering\Domain\ValueObject\Book;
use App\Ordering\Domain\ValueObject\BookCollection;
use App\Ordering\Domain\ValueObject\Money;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

final class BookCollectionType extends Type
{
    public const NAME = 'book_collection';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): BookCollection
    {
        if ($value instanceof BookCollection) {
            return $value;
        }

        if ($value === null || $value === '') {
            return new BookCollection([]);
        }

        $decoded = is_array($value)
            ? $value
            : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Invalid book collection');
        }

        /** @var array<int, array{title: string, author: string, amount: int, currency: string}> $decoded */
        $books = [];

        foreach ($decoded as $row) {
            $books[] = new Book(
                $row['title'],
                $row['author'],
                Money::fromCents($row['amount'], $row['currency'])
            );
        }

        return new BookCollection($books);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof BookCollection) {
            throw new InvalidArgumentException('Expected a book collection');
        }

        $rows = [];

        foreach ($value->getBooks() as $book) {
            $rows[] = [
                'title' => $book->getTitle(),
                'author' => $book->getAuthor(),
                'amount' => $book->getPrice()->getAmount(),
                'currency' => $book->getPrice()->getCurrency(),
            ];
        }

        return json_encode($rows, JSON_THROW_ON_ERROR);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
