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
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NodeConnectingVisitor;

/**
 * Optimizes a PHP syntax tree.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final readonly class PhpOptimizer
{
    /**
     * @param list<Node> $nodes
     *
     * @return list<Node>
     */
    public function optimize(array $nodes): array
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeConnectingVisitor());
        $nodes = $traverser->traverse($nodes);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new MergingStringYieldVisitor());
        $traverser->addVisitor(new MergingStringStreamWriteVisitor());
        $traverser->addVisitor(new MergingStringFwriteVisitor());

        return $traverser->traverse($nodes);
    }
}
