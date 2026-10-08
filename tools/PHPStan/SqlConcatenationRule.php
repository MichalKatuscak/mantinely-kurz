<?php

declare(strict_types=1);

namespace App\Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\UnionType;

/**
 * Do SQL staré administrace se hodnota nesmí přilepit bez ošetření
 * ("... WHERE id = '" . $id . "'"): to je SQL injection.
 *
 * Hlídá dotazy přes LegacyDb ($db->query(), exec(), one(), value()) a pomocné
 * funkce z src/Legacy/lib/db.php, jejichž SQL je poskládané tečkou nebo
 * z řetězce v uvozovkách s proměnnou. Každá část musí být bezpečná už na místě
 * dotazu: literál, $db->quote(), přetypování na číslo, pomocná funkce,
 * která hodnotu ošetří, nebo výraz, jehož nativní typ PHPStan zná jako číslo
 * či pevné řetězce bez apostrofů. Jinak pravidlo tok dat nesleduje: proměnná
 * bez takového typu je uvnitř dotazu chyba, celý dotaz v proměnné
 * ($db->query($sql)) pravidlo nekontroluje.
 *
 * @implements Rule<CallLike>
 */
final class SqlConcatenationRule implements Rule
{
    /**
     * Funkce, jejichž výsledek se smí přilepit do SQL:
     *
     * - db_escape(): obal nad $db->quote() (src/Legacy/lib/db.php),
     * - ids_to_sql(): každé ID v apostrofech, apostrofy uvnitř zdvojené
     *   (src/Legacy/lib/functions.php),
     * - db_now(): date('Y-m-d H:i:s') (src/Legacy/lib/db.php),
     * - intval(), floatval(): totéž co přetypování.
     *
     * db_escape_old() mezi nimi není: vrací hodnotu bez apostrofů, takže
     * bezpečná je jen uvnitř nich. date(), md5() a sha1() jsou bezpečné jen
     * s určitými argumenty, viz isSafeDate() a isSafeHash().
     */
    private const array SAFE_FUNCTIONS = ['db_escape', 'ids_to_sql', 'db_now', 'intval', 'floatval'];

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $sql = LegacySql::sqlArgument($node, $scope);
        if ($sql === null || !LegacySql::isComposed($sql)) {
            return [];
        }

        $parts = LegacySql::parts($sql);
        $unsafe = array_find_key($parts, fn (Expr|InterpolatedStringPart $part): bool => !$this->isSafe($part, $scope));
        if ($unsafe === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Do not concatenate values into SQL%s: %s (#%s); use $db->quote() or a cast.',
                LegacySql::context($scope),
                LegacySql::excerpt($parts, $unsafe),
                LegacySql::fingerprint($parts),
            ))
                ->identifier('mantinely.sqlConcatenation')
                ->build(),
        ];
    }

    private function isSafe(Expr|InterpolatedStringPart $part, Scope $scope): bool
    {
        if ($part instanceof InterpolatedStringPart) {
            return true;
        }

        return $this->isSafeExpression($part, $scope) || $this->hasSafeType($part, $scope);
    }

    private function isSafeExpression(Expr $part, Scope $scope): bool
    {
        return match (true) {
            $part instanceof String_,
            $part instanceof Int_,
            $part instanceof Float_,
            $part instanceof Cast\Int_,
            $part instanceof Cast\Double => true,
            // Tečka v závorkách: bezpečné musí být všechny části.
            $part instanceof Concat => $this->allSafe(LegacySql::parts($part), $scope),
            // Podmínka: bezpečné musí být obě větve (u ?: je první větví podmínka).
            $part instanceof Ternary => $this->isSafe($part->if ?? $part->cond, $scope)
                && $this->isSafe($part->else, $scope),
            $part instanceof MethodCall, $part instanceof NullsafeMethodCall => LegacySql::methodName($part) === 'quote'
                && LegacySql::isDb($part->var, $scope),
            $part instanceof CallLike => in_array(LegacySql::functionName($part), self::SAFE_FUNCTIONS, true)
                || $this->isSafeDate($part)
                || $this->isSafeHash($part),
            default => false,
        };
    }

    /**
     * Nativní typ je číslo (int, float, aritmetika s nimi, count()) nebo pevné
     * hodnoty, z nichž žádný řetězec neobsahuje apostrof ani zpětné lomítko
     * ($dir = $desc ? 'DESC' : 'ASC'). Typy z phpDoc se nepočítají: phpDoc
     * nikdo nekontroluje a ve staré administraci může lhát.
     */
    private function hasSafeType(Expr $part, Scope $scope): bool
    {
        $type = $scope->getNativeType($part);
        if (new UnionType([new IntegerType(), new FloatType()])->isSuperTypeOf($type)->yes()) {
            return true;
        }

        return $type->isConstantScalarValue()->yes() && array_all(
            $type->getConstantScalarValues(),
            static fn (mixed $value): bool => !is_string($value) || preg_match('/[\'\\\\]/', $value) === 0,
        );
    }

    /**
     * md5() a sha1() vrací šestnáctkové číslice, jen když druhý argument
     * ($binary) chybí nebo je literál false. Binární výstup může obsahovat
     * apostrof (md5('ffifdyop', true) obsahuje "'or'").
     */
    private function isSafeHash(CallLike $call): bool
    {
        if (!in_array(LegacySql::functionName($call), ['md5', 'sha1'], true) || $call->isFirstClassCallable()) {
            return false;
        }

        $args = $call->getArgs();
        if (count($args) === 1) {
            return $args[0]->name === null && !$args[0]->unpack;
        }

        return count($args) === 2
            && $args[1]->name === null
            && $args[1]->value instanceof ConstFetch
            && $args[1]->value->name->toLowerString() === 'false';
    }

    /**
     * date() s formátem v literálu bez apostrofů, uvozovek a zpětných lomítek
     * vrátí jen číslice a písmena z formátu (date('Y-m-d H:i:s')). Formát
     * zvenku bezpečný není: neznámé znaky vrátí date() beze změny.
     */
    private function isSafeDate(CallLike $call): bool
    {
        if (LegacySql::functionName($call) !== 'date' || $call->isFirstClassCallable()) {
            return false;
        }

        $format = $call->getArgs()[0] ?? null;

        return $format !== null
            && $format->name === null
            && $format->value instanceof String_
            && preg_match('/[\'"\\\\]/', $format->value->value) === 0;
    }

    /** @param list<Expr|InterpolatedStringPart> $parts */
    private function allSafe(array $parts, Scope $scope): bool
    {
        return array_all($parts, fn (Expr|InterpolatedStringPart $part): bool => $this->isSafe($part, $scope));
    }
}
