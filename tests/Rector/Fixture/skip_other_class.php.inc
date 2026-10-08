<?php

final class RevenueReport
{
    public function getAmountInCents(): int
    {
        return 0;
    }
}

$report = new RevenueReport();
$total = $report->getAmountInCents();

?>
