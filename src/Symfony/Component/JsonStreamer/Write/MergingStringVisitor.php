<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Write;

use PhpParser\Node;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Merges strings that are yielded consequently
 * to reduce the call instructions amount.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class MergingStringVisitor extends NodeVisitorAbstract
{
    private string $buffer = '';

    public function leaveNode(Node $node): int|Node|array|null
    {
        if (!$this->isMergeableNode($node)) {
            return null;
        }

        /** @var Node|null $next */
        $next = $node->getAttribute('next');

        if ($next && $this->isMergeableNode($next)) {
            $this->buffer .= $node->expr->value->value;

            return NodeVisitor::REMOVE_NODE;
        }

        $string = $this->buffer.$node->expr->value->value;
        $this->buffer = '';

        return new Expression(new Yield_(new String_($string)));
    }

    private function isMergeableNode(Node $node): bool
    {
        return $node instanceof Expression
            && $node->expr instanceof Yield_
            && $node->expr->value instanceof String_;
    }
}
