<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * SQLite databáze pro testy. Šablona se staví z migrací, jen když se migrace změnily.
 */
final class TestDatabase
{
    public static function file(): string
    {
        return self::projectDir().'/var/data_test.db';
    }

    public static function buildTemplate(): void
    {
        $template = self::template();
        $newest = max(array_map('filemtime', self::migrations()));

        if (is_file($template) && filemtime($template) >= $newest) {
            return;
        }

        @unlink(self::file());
        exec(
            sprintf('%s %s/bin/console doctrine:migrations:migrate --env=test --no-interaction --quiet 2>&1', PHP_BINARY, self::projectDir()),
            $output,
            $exitCode,
        );
        if ($exitCode !== 0) {
            throw new \RuntimeException("Migrations for the test database failed:\n".implode("\n", $output));
        }

        copy(self::file(), $template);
    }

    /** Vrátí databázi do stavu po migracích. Volá se před nastartováním jádra. */
    public static function reset(): void
    {
        copy(self::template(), self::file());
    }

    /** @return non-empty-list<string> */
    private static function migrations(): array
    {
        $files = glob(self::projectDir().'/migrations/*.php');

        return $files === false || $files === [] ? [__FILE__] : $files;
    }

    private static function template(): string
    {
        return self::projectDir().'/var/data_test.template.db';
    }

    private static function projectDir(): string
    {
        return dirname(__DIR__, 2);
    }
}
