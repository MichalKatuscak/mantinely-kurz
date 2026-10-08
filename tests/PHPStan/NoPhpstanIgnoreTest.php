<?php

declare(strict_types=1);

namespace App\Tests\PHPStan;

use Ergebnis\PHPStan\Rules\Files\NoPhpstanIgnoreRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Umlčení pravidla komentářem hlásí NoPhpstanIgnoreRule z ergebnis/phpstan-rules.
 * Je zapnuté v phpstan.neon i v phpstan-legacy.neon.
 *
 * @extends RuleTestCase<NoPhpstanIgnoreRule>
 */
final class NoPhpstanIgnoreTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoPhpstanIgnoreRule();
    }

    #[Test]
    public function reportsSilencedSqlConcatenation(): void
    {
        $this->analyse([__DIR__.'/data/sql-concatenation-ignored.php'], [
            [
                'Errors reported by phpstan/phpstan should not be ignored via "@phpstan-ignore", fix the error or use the baseline instead.',
                12,
            ],
            // V tomhle testu běží jen NoPhpstanIgnoreRule, takže komentář nemá co umlčet.
            ['No error with identifier mantinely.sqlConcatenation is reported on line 13.', 13],
        ]);
    }
}
