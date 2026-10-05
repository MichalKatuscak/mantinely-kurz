<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

final readonly class Email
{
    public function __construct(public string $value)
    {
        // Konstruktor jen validuje. Úpravu vstupu dělá fromUserInput().
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid e-mail address', $value));
        }
    }

    public static function fromUserInput(string $input): self
    {
        return new self(mb_strtolower(trim($input)));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
