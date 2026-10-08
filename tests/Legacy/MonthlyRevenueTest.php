<?php

declare(strict_types=1);

namespace App\Tests\Legacy;

use App\Legacy\Admin\ReportController;
use App\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\Drivers\TextDriver;
use Spatie\Snapshots\MatchesSnapshots;

/**
 * Charakterizační testy reportu měsíčních tržeb ze staré administrace.
 *
 * Zachycují, co report počítá dnes, včetně chyb (sleva se neodečítá, počítají se jen
 * zaplacené objednávky). Hodnoty jsou ve snapshotech v __snapshots__, nevymýšlejí se.
 * Snapshot se při refaktoringu nemění.
 */
final class MonthlyRevenueTest extends TestCase
{
    use MatchesSnapshots;

    private const array MONTHS = [
        'bez objednávek' => '2025-11',
        'se slevou' => '2025-10',
        'se stornem' => '2026-02',
        'přelom roku (prosinec)' => '2025-12',
        'přelom roku (leden)' => '2026-01',
    ];

    protected function setUp(): void
    {
        TestDatabase::reset();
        $pdo = new \PDO('sqlite:'.TestDatabase::file());
        $pdo->exec((string) file_get_contents(__DIR__.'/fixtures/report-months.sql'));

        $GLOBALS['LEGACY_DSN'] = 'sqlite:'.TestDatabase::file();
        unset($GLOBALS['db']);
        require_once __DIR__.'/../../src/Legacy/bootstrap.php';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['db'], $GLOBALS['LEGACY_DSN'], $_GET['month']);
    }

    #[Test]
    public function revenueTable(): void
    {
        $table = mb_str_pad('měsíc', 24).'tržba'."\n";
        foreach (self::MONTHS as $label => $month) {
            $table .= mb_str_pad($label, 24).\monthlyRevenue($month)." Kč\n";
        }

        $this->assertMatchesTextSnapshot($table);
    }

    #[Test]
    #[DataProvider('months')]
    public function reportPage(string $month): void
    {
        $_GET['month'] = $month;

        $html = (new ReportController())->monthlyAction();

        // Datum vygenerování je jediná nedeterministická část výstupu.
        $this->assertMatchesHtmlSnapshot(
            (string) preg_replace('/vygenerováno \d{1,2}\. \d{1,2}\. \d{4}/u', 'vygenerováno D. M. RRRR', (string) $html),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function months(): iterable
    {
        foreach (self::MONTHS as $month) {
            yield $month => [$month];
        }
    }

    /**
     * HTML stránky se ukládá tak, jak ho report vrátil. HtmlDriver knihovny ho před
     * porovnáním přeformátuje přes DOMDocument a výsledek závisí na verzi libxml
     * (jinde vyjdou znaky s diakritikou jako entity).
     */
    public function assertMatchesHtmlSnapshot(string $actual, ?string $id = null): void
    {
        $this->assertMatchesSnapshot($actual, new class extends TextDriver {
            public function extension(): string
            {
                return 'html';
            }
        }, $id);
    }
}
