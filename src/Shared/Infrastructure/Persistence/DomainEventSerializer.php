<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Domain\Event\DomainEvent;
use ReflectionClass;
use RuntimeException;

final class DomainEventSerializer
{
    public function __construct(
        private readonly PayloadNormalizerRegistry $normalizers,
    ) {
    }

    public function serialize(DomainEvent $event): string
    {
        $payload = [];
        $constructor = (new ReflectionClass($event))->getConstructor();

        if ($constructor === null) {
            throw new RuntimeException('Domain event ' . $event::class . ' has no constructor.');
        }

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $value = $event->{$name};
            $payload[$name] = is_object($value) ? $this->normalizers->export($value) : $value;
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

            $name = $typeName->getName();
            $value = $data[$parameter->getName()];
            $arguments[] = in_array($name, ['string', 'int', 'float', 'bool'], true)
                ? $value
                : $this->normalizers->import($name, $value);
        }

        /** @var DomainEvent $event */
        $event = $reflection->newInstanceArgs($arguments);

        return $event;
    }
}
