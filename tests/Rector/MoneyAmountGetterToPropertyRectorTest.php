<?php

declare(strict_types=1);

namespace App\Tests\Rector;

use App\Tools\Rector\MoneyAmountGetterToPropertyRector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

#[CoversClass(MoneyAmountGetterToPropertyRector::class)]
final class MoneyAmountGetterToPropertyRectorTest extends AbstractRectorTestCase
{
    #[Test]
    #[DataProvider('provideFixtures')]
    public function rewritesGetterToProperty(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    /** @return \Iterator<array{string}> */
    public static function provideFixtures(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__.'/Fixture', '*.php.inc');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__.'/config/configured_rule.php';
    }
}
