<?php

declare(strict_types=1);

namespace App\Tools\PHPStan;

use App\Legacy\lib\LegacyDb;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;

/**
 * Společné rozpoznání dotazů staré administrace pro LegacyTableWriteRule
 * a SqlConcatenationRule.
 *
 * Databáze je objekt LegacyDb. Stará administrace je bez typů (global $db),
 * proto se pozná i podle jména: proměnná $db, vlastnost $this->db,
 * $GLOBALS['db'] a legacy_db(). Dotaz jde buď přes metodu objektu, nebo přes pomocné
 * funkce z src/Legacy/lib/db.php.
 */
final class LegacySql
{
    /** Metody LegacyDb, které spustí SQL z prvního argumentu. */
    private const array QUERY_METHODS = ['query', 'exec', 'one', 'value'];

    /** Pomocné funkce z db.php: jméno => pozice argumentu s kusem SQL. */
    private const array QUERY_FUNCTIONS = [
        'db_query' => 0,
        'db_exec' => 0,
        'db_one' => 0,
        'db_value' => 0,
        // db_update($table, $data, $where): $where je hotový kus SQL.
        'db_update' => 2,
    ];

    /** Pomocné funkce z db.php, které zapíšou do tabulky z prvního argumentu. */
    private const array TABLE_FUNCTIONS = ['db_insert', 'db_update'];

    /** Nejdelší výňatek dotazu v hlášce (znaků). */
    private const int EXCERPT_MAX = 100;

    /**
     * Výraz s kusem SQL, který volání spustí, nebo null, když to není dotaz.
     */
    public static function sqlArgument(CallLike $call, Scope $scope): ?Expr
    {
        // $db?->query() pravidla dostanou dvakrát: jako NullsafeMethodCall
        // a jako MethodCall, kterým ho PHPStan analyzuje. Stačí MethodCall.
        if ($call instanceof MethodCall) {
            return in_array(self::methodName($call), self::QUERY_METHODS, true) && self::isDb($call->var, $scope)
                ? self::argument($call, 0)
                : null;
        }

        $function = self::functionName($call);
        if ($function === null || !array_key_exists($function, self::QUERY_FUNCTIONS)) {
            return null;
        }

        return self::argument($call, self::QUERY_FUNCTIONS[$function]);
    }

    /**
     * Tabulka, do které zapíše db_insert()/db_update(), když je zadaná řetězcem.
     */
    public static function writtenTable(CallLike $call): ?string
    {
        if (!in_array(self::functionName($call), self::TABLE_FUNCTIONS, true)) {
            return null;
        }

        $table = self::argument($call, 0);

        return $table instanceof String_ ? strtolower(trim($table->value)) : null;
    }

    /**
     * Objekt databáze: podle typu LegacyDb, v kódu bez typů podle jména.
     */
    public static function isDb(Expr $expr, Scope $scope): bool
    {
        if (new ObjectType(LegacyDb::class)->isSuperTypeOf($scope->getType($expr))->yes()) {
            return true;
        }

        return match (true) {
            $expr instanceof Variable => $expr->name === 'db',
            $expr instanceof PropertyFetch, $expr instanceof NullsafePropertyFetch => $expr->name instanceof Identifier
                && $expr->name->toString() === 'db',
            $expr instanceof ArrayDimFetch => $expr->var instanceof Variable
                && $expr->var->name === 'GLOBALS'
                && $expr->dim instanceof String_
                && $expr->dim->value === 'db',
            $expr instanceof FuncCall => self::functionName($expr) === 'legacy_db',
            default => false,
        };
    }

    /**
     * Části dotazu: operandy tečky (rozbalené do hloubky) a části řetězce
     * v uvozovkách s proměnnými ("... '$id'"). Jiný výraz je jedna část.
     *
     * @return list<Expr|InterpolatedStringPart>
     */
    public static function parts(Expr $sql): array
    {
        if ($sql instanceof Concat) {
            return [...self::parts($sql->left), ...self::parts($sql->right)];
        }

        if ($sql instanceof InterpolatedString) {
            return array_values($sql->parts);
        }

        return [$sql];
    }

    /**
     * Krátký výňatek dotazu do hlášky, kolem části $focus: řetězce
     * v uvozovkách se slitými mezerami, ostatní části jako PHP, spojené
     * tečkou. Výňatek dělá každou položku baseline jedinečnou, takže nový
     * výskyt v souboru se starými se nahlásí na svém řádku. Závisí jen na
     * kódu volání, ne na čísle řádku: posun kódu baseline nerozbije. Změnu
     * mimo výňatek zachytí otisk, viz fingerprint().
     *
     * @param list<Expr|InterpolatedStringPart> $parts
     */
    public static function excerpt(array $parts, int $focus, int $before = 30, int $after = 40): string
    {
        $pieces = self::pieces($parts);
        $full = implode(' . ', $pieces);
        $length = mb_strlen($full);
        $offset = mb_strlen(implode(' . ', array_slice($pieces, 0, $focus))) + ($focus > 0 ? 3 : 0);
        $to = min($length, $offset + mb_strlen($pieces[$focus] ?? '') + $after);

        // Začátek dotazu se vynechá, jen když se výňatek jinak nevejde.
        $from = 0;
        if ($to > self::EXCERPT_MAX) {
            $from = max(0, $offset - $before);
            // Začít na začátku slova, ne uprostřed.
            $space = mb_strpos($full, ' ', $from);
            if ($from > 0 && mb_substr($full, $from - 1, 1) !== ' ' && $space !== false && $space < $offset) {
                $from = $space + 1;
            }
        }
        $to = min($to, $from + self::EXCERPT_MAX);
        $text = mb_substr($full, $from, $to - $from);
        if ($from > 0 && str_starts_with($text, '. ')) {
            $text = substr($text, 2);
        }

        return ($from > 0 ? '…' : '').$text.($to < $length ? '…' : '');
    }

    /**
     * Kde dotaz je: „ in Třída::metoda()“ nebo „ in funkce()“, v kódu mimo
     * funkci prázdné. Odliší stejné dotazy v různých funkcích jednoho souboru.
     */
    public static function context(Scope $scope): string
    {
        $function = $scope->getFunction();
        if ($function === null) {
            return '';
        }

        $class = $scope->getClassReflection();
        $name = $function->getName();
        if ($class !== null && !$class->isAnonymous()) {
            return sprintf(' in %s::%s()', $class->getNativeReflection()->getShortName(), $name);
        }

        $short = strrchr($name, '\\');

        return sprintf(' in %s()', $short === false ? $name : substr($short, 1));
    }

    /**
     * Otisk celého dotazu do hlášky: prvních 8 znaků sha1 z celého
     * normalizovaného výrazu (stejně jako výňatek, ale bez zkrácení).
     * Úprava starého dotazu kdekoli, i mimo výňatek, změní hlášku, takže ji
     * baseline nepohltí. Mezery a konce řádků jsou slité, otisk je tedy
     * stejný v checkoutu s CRLF i s LF.
     *
     * @param list<Expr|InterpolatedStringPart> $parts
     */
    public static function fingerprint(array $parts): string
    {
        return substr(sha1(implode(' . ', self::pieces($parts))), 0, 8);
    }

    /**
     * Části dotazu jako text: řetězce v uvozovkách se slitými mezerami,
     * ostatní části jako PHP z pretty-printeru.
     *
     * @param list<Expr|InterpolatedStringPart> $parts
     *
     * @return list<string>
     */
    private static function pieces(array $parts): array
    {
        $printer = new Standard();

        return array_map(
            static fn (Expr|InterpolatedStringPart $part): string => match (true) {
                $part instanceof String_, $part instanceof InterpolatedStringPart => '"'.self::squash($part->value).'"',
                // Závorky jako ve zdroji, jinak by "a" . $x ? 'b' : 'c' četl člověk špatně.
                $part instanceof Ternary, $part instanceof BinaryOp => '('.self::squash($printer->prettyPrintExpr($part)).')',
                default => self::squash($printer->prettyPrintExpr($part)),
            },
            $parts,
        );
    }

    /**
     * Slije mezery a konce řádků do jedné mezery. Pretty-printer vypíše konec
     * řádku uvnitř řetězce v uvozovkách jako escape \r\n nebo \n, proto se
     * sjednotí i escape (jinak by se otisk lišil mezi CRLF a LF checkoutem).
     */
    private static function squash(string $text): string
    {
        $text = str_replace(['\r\n', '\r'], '\n', $text);

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    /**
     * Je dotaz poskládaný z částí (tečkou nebo proměnnou v řetězci)?
     */
    public static function isComposed(Expr $sql): bool
    {
        return $sql instanceof Concat || $sql instanceof InterpolatedString;
    }

    public static function methodName(MethodCall|NullsafeMethodCall $call): ?string
    {
        return $call->name instanceof Identifier ? $call->name->toLowerString() : null;
    }

    public static function functionName(CallLike $call): ?string
    {
        return $call instanceof FuncCall && $call->name instanceof Name
            ? $call->name->toLowerString()
            : null;
    }

    private static function argument(CallLike $call, int $position): ?Expr
    {
        if ($call->isFirstClassCallable()) {
            return null;
        }

        $arg = $call->getArgs()[$position] ?? null;
        if (!$arg instanceof Arg || $arg->unpack || $arg->name !== null) {
            return null;
        }

        return $arg->value;
    }
}
