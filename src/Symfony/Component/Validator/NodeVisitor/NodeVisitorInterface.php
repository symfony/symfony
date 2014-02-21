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
    /**
     * Called at the beginning of a traversal.
     *
     * @param Node[]                    $nodes   A list of Node instances
     * @param ExecutionContextInterface $context The execution context
     *
     * @return Boolean Whether to continue the traversal
     */
    public function beforeTraversal($nodes, ExecutionContextInterface $context);

    /**
     * Called at the end of a traversal.
     *
     * @param Node[]                    $nodes   A list of Node instances
     * @param ExecutionContextInterface $context The execution context
     */
    public function afterTraversal($nodes, ExecutionContextInterface $context);

    /**
     * Called for each node during a traversal.
     *
     * @param Node                      $node    The current node
     * @param ExecutionContextInterface $context The execution context
     *
     * @return Boolean Whether to traverse the node's successor nodes
     */
    public function visit(Node $node, ExecutionContextInterface $context);
}
