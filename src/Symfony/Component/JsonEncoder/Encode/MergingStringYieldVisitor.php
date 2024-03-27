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
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;

/**
 * Merges strings that are yielded consequently.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class MergingStringYieldVisitor extends MergingStringVisitor
{
    protected function isMergeableNode(Node $node): bool
    {
        return $node instanceof Expression
            && $node->expr instanceof Yield_
            && $node->expr->value instanceof String_;
    }

    protected function getStringToMerge(Node $node): string
    {
        return $node->expr->value->value;
    }

    protected function getMergedNode(string $merged): Stmt
    {
        return new Expression(new Yield_(new String_($merged)));
    }
}
