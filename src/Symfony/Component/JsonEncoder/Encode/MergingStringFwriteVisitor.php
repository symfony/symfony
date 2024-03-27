<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Encode;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;

/**
 * Merges strings that are written consequently into a resource.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class MergingStringFwriteVisitor extends MergingStringVisitor
{
    protected function isMergeableNode(Node $node): bool
    {
        return $node instanceof Expression
            && $node->expr instanceof FuncCall
            && 'fwrite' === (string) $node->expr->name
            && ($arg0 = ($node->expr->args[0] ?? null)) instanceof Arg
            && ($arg1 = ($node->expr->args[1] ?? null)) instanceof Arg
            && $arg0->value instanceof Variable
            && 'stream' === $arg0->value->name
            && $arg1->value instanceof String_;
    }

    protected function getStringToMerge(Node $node): string
    {
        return $node->expr->args[1]->value->value;
    }

    protected function getMergedNode(string $merged): Stmt
    {
        return new Expression(new FuncCall(new FullyQualified('fwrite'), [new Arg(new Variable('stream')), new Arg(new String_($merged))]));
    }
}
