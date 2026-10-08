<?php

declare(strict_types=1);

namespace App\Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Stará administrace nesmí zapisovat do tabulek orders a stock_items přímo
 * v SQL. Stav objednávky a rezervace patří doméně (metody agregátů, příkazy
 * a události), jinak se obejdou přechody stavů, události a uvolnění rezervací.
 * Hláška záměrně neradí konkrétní příkaz: nemá napovídat řešení jednoho úkolu.
 *
 * Hlídá dotazy přes LegacyDb ($db->exec(), query(), one(), value()) a pomocné
 * funkce z src/Legacy/lib/db.php. Zápis se pozná podle řetězce v dotazu:
 * některá jeho část začíná UPDATE/INSERT INTO/DELETE FROM jedné z tabulek.
 * Tabulku poskládanou z proměnné pravidlo nevidí.
 *
 * @implements Rule<CallLike>
 */
final class LegacyTableWriteRule implements Rule
{
    private const string WRITE = '/^\s*(UPDATE\s+(orders|stock_items)\b|INSERT\s+INTO\s+(orders|stock_items)\b|DELETE\s+FROM\s+(orders|stock_items)\b)/i';

    private const array TABLES = ['orders', 'stock_items'];

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $write = $this->helperWrite($node) ?? $this->writeInSql($node, $scope);
        if ($write === null) {
            return [];
        }

        [$table, $excerpt, $fingerprint] = $write;

        return [
            RuleErrorBuilder::message(sprintf(
                'Do not write table %s from legacy code%s: %s (#%s); use the domain (aggregate methods, commands and events) instead.',
                $table,
                LegacySql::context($scope),
                $excerpt,
                $fingerprint,
            ))
                ->identifier('mantinely.legacyTableWrite')
                ->build(),
        ];
    }

    /**
     * db_insert('orders', ...) / db_update('stock_items', ...).
     *
     * @return array{string, string, string}|null tabulka, výňatek a otisk všech argumentů
     */
    private function helperWrite(CallLike $call): ?array
    {
        $table = LegacySql::writtenTable($call);
        if ($table === null || !in_array($table, self::TABLES, true)) {
            return null;
        }

        return [
            $table,
            sprintf("%s('%s', …)", LegacySql::functionName($call), $table),
            LegacySql::fingerprint(array_values(array_map(static fn (Arg $arg): Expr => $arg->value, $call->getArgs()))),
        ];
    }

    /**
     * Zápis v SQL dotazu: tabulka, výňatek od části, která zápis začíná,
     * a otisk celého dotazu.
     *
     * @return array{string, string, string}|null
     */
    private function writeInSql(CallLike $call, Scope $scope): ?array
    {
        $sql = LegacySql::sqlArgument($call, $scope);
        if ($sql === null) {
            return null;
        }

        $parts = LegacySql::parts($sql);
        foreach ($parts as $index => $part) {
            if (!$part instanceof String_ && !$part instanceof InterpolatedStringPart) {
                continue;
            }

            if (preg_match(self::WRITE, $part->value, $match) === 1) {
                // Jméno tabulky je ve skupině 2 (UPDATE), 3 (INSERT) nebo 4 (DELETE).
                return [
                    strtolower(implode('', array_slice($match, 2))),
                    LegacySql::excerpt($parts, $index, 0, 60),
                    LegacySql::fingerprint($parts),
                ];
            }
        }

        return null;
    }
}
