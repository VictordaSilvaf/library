<?php

declare(strict_types=1);

namespace App\Ordering\Domain\ValueObject;

class Money
{
    public function __construct(
        private readonly float $amount,
        private readonly string $currency,
    ) {}

    public static function create(float $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function add(Money $other)
    {
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other)
    {
        return new self($this->amount - $other->amount, $this->currency);
    }
}
