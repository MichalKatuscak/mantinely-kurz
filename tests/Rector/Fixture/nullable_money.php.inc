<?php

use App\SharedKernel\Domain\Money;

function discountInCents(?Money $discount): ?int
{
    return $discount?->getAmountInCents();
}

?>
-----
<?php

use App\SharedKernel\Domain\Money;

function discountInCents(?Money $discount): ?int
{
    return $discount?->amountInCents;
}

?>
