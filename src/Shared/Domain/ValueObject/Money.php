<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

#[ORM\Embeddable]
class Money
{
    private function __construct(
        #[ORM\Column(type: 'integer')]
        private readonly int $amount,
        #[ORM\Column(type: 'string', length: 3)]
        private readonly string $currency,
    ) {
    }

    public static function create(string $amount, string $currency): self
    {
        return new self(self::toCents($amount), $currency);
    }

    public static function fromCents(int $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currencies must be the same');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currencies must be the same');
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    private static function toCents(string $amount): int
    {
        if (preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid money amount');
        }

        $units = (int) $matches[2];
        $fraction = str_pad($matches[3] ?? '', 2, '0');
        $cents = ($units * 100) + (int) $fraction;

        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }
}
