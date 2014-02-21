<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\NodeTraverser;

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface;

/**
 * Traverses the nodes of the validation graph.
 *
 * You can attach visitors to the traverser that are invoked during the
 * traversal. Before starting the traversal, the
 * {@link \Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface::beforeTraversal()}
 * method of each visitor is called. For each node in the graph, the
 * {@link \Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface::visit()}
 * of each visitor is called. At the end of the traversal, the traverser invokes
 * {@link \Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface::afterTraversal()}
 * on each visitor.
 *
 * The visitors should be called in the same order in which they are added to
 * the traverser.
 *
 * The validation graph typically contains nodes of the following types:
 *
 *  - {@link \Symfony\Component\Validator\Node\ClassNode}:
 *    An object with associated class metadata
 *  - {@link \Symfony\Component\Validator\Node\PropertyNode}:
 *    A property value with associated property metadata
 *  - {@link \Symfony\Component\Validator\Node\GenericNode}:
 *    A generic value with associated constraints
 *  - {@link \Symfony\Component\Validator\Node\CollectionNode}:
 *    A traversable collection
 *
 * Generic nodes are mostly useful when you want to validate a value that has
 * neither associated class nor property metadata. Generic nodes usually come
 * with {@link \Symfony\Component\Validator\Mapping\GenericMetadata}, that
 * contains the constraints that the value should be validated against.
 *
 * Whenever a class, property or generic node is validated that contains a
 * traversable value which should be traversed (according to the
 * {@link \Symfony\Component\Validator\Mapping\TraversalStrategy} specified
 * in the node or its metadata), a new
 * {@link \Symfony\Component\Validator\Node\CollectionNode} will be attached
 * to the node graph.
 *
 * For example:
 *
 *     (TagList:ClassNode)
 *                \
 *        (TagList:CollectionNode)
 *
 * When writing custom visitors, be aware that collection nodes usually contain
 * values that have already been passed to the visitor before through a class
 * node, a property node or a generic node.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface NodeTraverserInterface
{
    /**
     * Adds a new visitor to the traverser.
     *
     * Visitors that have already been added before are ignored.
     *
     * @param NodeVisitorInterface $visitor The visitor to add
     */
    public function addVisitor(NodeVisitorInterface $visitor);

    /**
     * Removes a visitor from the traverser.
     *
     * Non-existing visitors are ignored.
     *
     * @param NodeVisitorInterface $visitor The visitor to remove
     */
    public function removeVisitor(NodeVisitorInterface $visitor);

    /**
     * Traverses the given nodes in the given context.
     *
     * @param Node[]                    $nodes   The nodes to traverse
     * @param ExecutionContextInterface $context The validation context
     */
    public function traverse(array $nodes, ExecutionContextInterface $context);
}
