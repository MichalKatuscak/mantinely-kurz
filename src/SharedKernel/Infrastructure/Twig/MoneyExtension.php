<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Twig;

use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use Twig\Attribute\AsTwigFilter;

final class MoneyExtension
{
    /** Formát pro šablony: 1 000,00 Kč. */
    #[AsTwigFilter('money')]
    public function format(Money $money): string
    {
        $symbol = match ($money->currency) {
            Currency::CZK => 'Kč',
            Currency::EUR => '€',
            Currency::USD => '$',
        };

        return sprintf(
            '%s %s',
            number_format($money->getAmountInCents() / 100, 2, ',', ' '),
            $symbol,
        );
    }
}
