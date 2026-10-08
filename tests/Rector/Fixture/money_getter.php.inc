<?php

use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;

$money = new Money(100_00, Currency::CZK);
$cents = $money->getAmountInCents();

?>
-----
<?php

use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;

$money = new Money(100_00, Currency::CZK);
$cents = $money->amountInCents;

?>
