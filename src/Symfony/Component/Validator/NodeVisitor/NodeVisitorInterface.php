<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\NodeVisitor;

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Node\Node;

/**
 * A node visitor invoked by the node traverser.
 *
 * At the beginning of the traversal, the method {@link beforeTraversal()} is
 * called. For each traversed node, the method {@link visit()} is called. At
 * last, the method {@link afterTraversal()} is called when the traversal is
 * complete.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see \Symfony\Component\Validator\NodeTraverser\NodeTraverserInterface
 */
interface NodeVisitorInterface
{
    public function beforeTraversal(array $nodes, ExecutionContextInterface $context);

    public function afterTraversal(array $nodes, ExecutionContextInterface $context);

    public function visit(Node $node, ExecutionContextInterface $context);
}
