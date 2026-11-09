<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

class Uuid
{
    private $value;

    public function __construct(string $value)
    {
        self::validate($value);
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public static function validate(string $value)
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Invalid UUID format');
        }
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value)) {
            throw new \InvalidArgumentException('Invalid UUID format');
        }
        return true;
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }
}