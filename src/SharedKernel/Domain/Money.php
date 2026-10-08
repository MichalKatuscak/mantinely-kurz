<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Money
{
    public function __construct(
        #[ORM\Column]
        public int $amountInCents,
        #[ORM\Column(enumType: Currency::class)]
        public Currency $currency,
    ) {
        if ($amountInCents < 0) {
            throw new \InvalidArgumentException('Money cannot be negative');
        }
    }

    /**
     * Přežitek z doby, kdy Money mělo soukromé vlastnosti. Nový kód čte $amountInCents.
     */
    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            // Vědomá zkratka jako v knize: pojmenovaná výjimka by v Shared Kernelu
            // znamenala další sdílený typ.
            throw new \DomainException(
                "Cannot add {$this->currency->value} and {$other->currency->value}"
            );
        }

        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function subtract(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \DomainException(
                "Cannot subtract {$other->currency->value} from {$this->currency->value}"
            );
        }

        return new self($this->amountInCents - $other->amountInCents, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amountInCents * $factor, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amountInCents === $other->amountInCents
            && $this->currency === $other->currency;
    }
}
