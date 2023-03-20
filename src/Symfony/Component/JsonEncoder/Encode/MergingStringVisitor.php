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
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Abstraction that merges strings which are written
 * consequently to reduce the call instructions amount.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
abstract class MergingStringVisitor extends NodeVisitorAbstract
{
    private string $buffer = '';

    abstract protected function isMergeableNode(Node $node): bool;

    abstract protected function getStringToMerge(Node $node): string;

    abstract protected function getMergedNode(string $merged): Stmt;

    final public function leaveNode(Node $node): int|Node|array|null
    {
        if (!$this->isMergeableNode($node)) {
            return null;
        }

        /** @var Node|null $next */
        $next = $node->getAttribute('next');

        if ($next && $this->isMergeableNode($next)) {
            $this->buffer .= $this->getStringToMerge($node);

            return NodeTraverser::REMOVE_NODE;
        }

        $string = $this->buffer.$this->getStringToMerge($node);
        $this->buffer = '';

        return $this->getMergedNode($string);
    }
}
