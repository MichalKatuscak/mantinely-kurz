<?php

declare(strict_types=1);

namespace App\Tools\Rector;

use App\SharedKernel\Domain\Money;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Přežitek Money::getAmountInCents() → veřejná vlastnost amountInCents.
 */
final class MoneyAmountGetterToPropertyRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace Money::getAmountInCents() with the public readonly property amountInCents',
            [new CodeSample('$cents = $money->getAmountInCents();', '$cents = $money->amountInCents;')],
        );
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [MethodCall::class, NullsafeMethodCall::class];
    }

    /** @param MethodCall|NullsafeMethodCall $node */
    public function refactor(Node $node): ?Node
    {
        if (!$this->isName($node->name, 'getAmountInCents')) {
            return null;
        }

        if (!$this->isObjectType($node->var, new ObjectType(Money::class))) {
            return null;
        }

        return $node instanceof NullsafeMethodCall
            ? new NullsafePropertyFetch($node->var, 'amountInCents')
            : new PropertyFetch($node->var, 'amountInCents');
    }
}
