<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

enum Currency: string
{
    case CZK = 'CZK';
    case EUR = 'EUR';
    case USD = 'USD';
}
