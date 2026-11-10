<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Catalog\Domain\ValueObject\Image;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Catalog\Domain\ValueObject\Sku;
use App\Ordering\Domain\ValueObject\Product;
use App\Ordering\Domain\ValueObject\ProductCollection;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Uuid;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionClass;
use RuntimeException;

final class DomainEventSerializer
{
    public function serialize(DomainEvent $event): string
    {
        $payload = [];
        $constructor = (new ReflectionClass($event))->getConstructor();

        if ($constructor === null) {
            throw new RuntimeException('Domain event ' . $event::class . ' has no constructor.');
        }

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $payload[$name] = $this->export($event->{$name});
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    public function deserialize(string $type, string $payload): DomainEvent
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $reflection = new ReflectionClass($type);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || !is_a($type, DomainEvent::class, true)) {
            throw new RuntimeException('Cannot deserialize domain event ' . $type . '.');
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $typeName = $parameter->getType();

            if (!$typeName instanceof \ReflectionNamedType) {
                throw new RuntimeException('Unsupported event parameter ' . $parameter->getName() . '.');
            }

            $arguments[] = $this->import($typeName->getName(), $data[$parameter->getName()]);
        }

        /** @var DomainEvent $event */
        $event = $reflection->newInstanceArgs($arguments);

        return $event;
    }

    private function export(mixed $value): mixed
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Money) {
            return ['amount' => $value->getAmount(), 'currency' => $value->getCurrency()];
        }

        if ($value instanceof Uuid) {
            return $value->getValue();
        }

        if ($value instanceof Sku) {
            return $value->value();
        }

        if ($value instanceof Product) {
            return [
                'title' => $value->getTitle(),
                'author' => $value->getAuthor(),
                'price' => $this->export($value->getPrice()),
            ];
        }

        if ($value instanceof ProductCollection) {
            return array_map(fn (Product $product): mixed => $this->export($product), $value->getProducts());
        }

        if ($value instanceof Image) {
            return ['url' => $value->url];
        }

        if ($value instanceof ImageCollection) {
            return array_map(fn (Image $image): mixed => $this->export($image), $value->all());
        }

        return $value;
    }

    private function import(string $type, mixed $value): mixed
    {
        if ($type === 'string' || $type === 'int' || $type === 'float' || $type === 'bool') {
            return $value;
        }

        if ($type === DateTimeImmutable::class) {
            return new DateTimeImmutable((string) $value);
        }

        if (is_a($type, BackedEnum::class, true)) {
            return $type::from($value);
        }

        if ($type === Money::class && is_array($value)) {
            return Money::fromCents((int) $value['amount'], (string) $value['currency']);
        }

        if (is_a($type, Uuid::class, true)) {
            return new $type((string) $value);
        }

        if ($type === Sku::class) {
            return new Sku((string) $value);
        }

        if ($type === Image::class && is_array($value)) {
            return new Image((string) $value['url']);
        }

        if ($type === Product::class && is_array($value)) {
            return new Product(
                (string) $value['title'],
                (string) $value['author'],
                $this->import(Money::class, $value['price']),
            );
        }

        if ($type === ProductCollection::class && is_array($value)) {
            return new ProductCollection(array_map(
                fn (mixed $product): Product => $this->import(Product::class, $product),
                $value,
            ));
        }

        if ($type === ImageCollection::class && is_array($value)) {
            return new ImageCollection(...array_map(
                fn (mixed $image): Image => $this->import(Image::class, $image),
                $value,
            ));
        }

        throw new RuntimeException('Cannot import value of type ' . $type . '.');
    }
}
