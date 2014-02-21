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
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Exception\UnsupportedMetadataException;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Mapping\TraversalStrategy;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Node\ClassNode;
use Symfony\Component\Validator\Node\CollectionNode;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\Node\PropertyNode;
use Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface;

/**
 * Non-recursive implementation of {@link NodeTraverserInterface}.
 *
 * This implementation uses a Depth First algorithm to traverse the node
 * graph. Instead of loading the complete node graph into memory before the
 * traversal, the traverser only expands the successor nodes of a node once
 * that node is traversed. For example, when traversing a class node, the
 * nodes for all constrained properties of that class are loaded into memory.
 * When the traversal of the class node is over, the node is discarded.
 *
 * Next, one of the class' property nodes is traversed. At that point, the
 * successor nodes of that property node (a class node, if the property should
 * be cascaded, or a collection node, if the property should be traversed) are
 * loaded into memory. As soon as the traversal of the property node is over,
 * it is discarded as well.
 *
 * This leads to an average memory consumption of O(log N * B), where N is the
 * number of nodes in the graph and B is the average number of successor nodes
 * of a node.
 *
 * In order to maintain a small execution stack, nodes are not validated
 * recursively, but iteratively. Internally, a stack is used to store all the
 * nodes that should be processed. Whenever a node is traversed, its successor
 * nodes are put on the stack. The traverser keeps fetching and traversing nodes
 * from the stack until the stack is empty and all nodes have been traversed.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see NodeTraverserInterface
 */
class NonRecursiveNodeTraverser implements NodeTraverserInterface
{
    /**
     * @var NodeVisitorInterface[]
     */
    private $visitors;

    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

    /**
     * @var Boolean
     */
    private $traversalStarted = false;

    /**
     * Creates a new traverser.
     *
     * @param MetadataFactoryInterface $metadataFactory The metadata factory
     */
    public function __construct(MetadataFactoryInterface $metadataFactory)
    {
        $this->visitors = new \SplObjectStorage();
        $this->nodeStack = new \SplStack();
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function addVisitor(NodeVisitorInterface $visitor)
    {
        $this->visitors->attach($visitor);
    }

    /**
     * {@inheritdoc}
     */
    public function removeVisitor(NodeVisitorInterface $visitor)
    {
        $this->visitors->detach($visitor);
    }

    /**
     * {@inheritdoc}
     */
    public function traverse(array $nodes, ExecutionContextInterface $context)
    {
        // beforeTraversal() and afterTraversal() are only executed for the
        // top-level call of traverse()
        $isTopLevelCall = !$this->traversalStarted;

        if ($isTopLevelCall) {
            // Remember that the traversal was already started for the case of
            // recursive calls to traverse()
            $this->traversalStarted = true;

            foreach ($this->visitors as $visitor) {
                $visitor->beforeTraversal($nodes, $context);
            }
        }

        // This stack contains all the nodes that should be traversed
        // A stack is used rather than a queue in order to traverse the graph
        // in a Depth First approach (the last added node is processed first).
        // In this way, the order in which the nodes are passed to the visitors
        // is similar to a recursive implementation (except that the successor
        // nodes of a node are traversed right-to-left instead of left-to-right).
        $nodeStack = new \SplStack();

        foreach ($nodes as $node) {
            // Push a node to the stack and immediately process it. This way,
            // the successor nodes are traversed before the next node in $nodes
            $nodeStack->push($node);

            // Fetch nodes from the stack and traverse them until no more nodes
            // are left. Then continue with the next node in $nodes.
            while (!$nodeStack->isEmpty()) {
                $node = $nodeStack->pop();

                if ($node instanceof ClassNode) {
                    $this->traverseClassNode($node, $context, $nodeStack);
                } elseif ($node instanceof CollectionNode) {
                    $this->traverseCollectionNode($node, $context, $nodeStack);
                } else {
                    $this->traverseNode($node, $context, $nodeStack);
                }
            }
        }

        if ($isTopLevelCall) {
            foreach ($this->visitors as $visitor) {
                $visitor->afterTraversal($nodes, $context);
            }

            // Put the traverser back into its initial state
            $this->traversalStarted = false;
        }
    }

    /**
     * Executes the {@link NodeVisitorInterface::visit()} method of each
     * visitor.
     *
     * @param Node                      $node    The visited node
     * @param ExecutionContextInterface $context The current execution context
     *
     * @return Boolean Whether to traverse the node's successor nodes
     */
    private function visit(Node $node, ExecutionContextInterface $context)
    {
        foreach ($this->visitors as $visitor) {
            if (false === $visitor->visit($node, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Traverses a class node.
     *
     * At first, each visitor is invoked for this node. Then, unless any
     * of the visitors aborts the traversal by returning false, a property
     * node is put on the node stack for each constrained property of the class.
     * At last, if the class is traversable and should be traversed according
     * to the selected traversal strategy, a new collection node is put on the
     * stack.
     *
     * @param ClassNode                 $node      The class node
     * @param ExecutionContextInterface $context   The current execution context
     * @param \SplStack                 $nodeStack The stack for storing the
     *                                             successor nodes
     *
     * @throws UnsupportedMetadataException If a property metadata does not
     *                                      implement {@link PropertyMetadataInterface}
     *
     * @see ClassNode
     * @see PropertyNode
     * @see CollectionNode
     * @see TraversalStrategy
     */
    private function traverseClassNode(ClassNode $node, ExecutionContextInterface $context, \SplStack $nodeStack)
    {
        // Visitors have two possibilities to influence the traversal:
        //
        // 1. If a visitor's visit() method returns false, the traversal is
        //    skipped entirely.
        // 2. If a visitor's visit() method removes a group from the node,
        //    that group will be skipped in the subtree of that node.

        if (false === $this->visit($node, $context)) {
            return;
        }

        if (0 === count($node->groups)) {
            return;
        }

        foreach ($node->metadata->getConstrainedProperties() as $propertyName) {
            foreach ($node->metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
                if (!$propertyMetadata instanceof PropertyMetadataInterface) {
                    throw new UnsupportedMetadataException(sprintf(
                        'The property metadata instances should implement '.
                        '"Symfony\Component\Validator\Mapping\PropertyMetadataInterface", '.
                        'got: "%s".',
                        is_object($propertyMetadata) ? get_class($propertyMetadata) : gettype($propertyMetadata)
                    ));
                }

                $nodeStack->push(new PropertyNode(
                    $node->value,
                    $propertyMetadata->getPropertyValue($node->value),
                    $propertyMetadata,
                    $node->propertyPath
                        ? $node->propertyPath.'.'.$propertyName
                        : $propertyName,
                    $node->groups,
                    $node->cascadedGroups
                ));
            }
        }

        $traversalStrategy = $node->traversalStrategy;

        // If no specific traversal strategy was requested when this method
        // was called, use the traversal strategy of the class' metadata
        if ($traversalStrategy & TraversalStrategy::IMPLICIT) {
            // Keep the STOP_RECURSION flag, if it was set
            $traversalStrategy = $node->metadata->getTraversalStrategy()
                | ($traversalStrategy & TraversalStrategy::STOP_RECURSION);
        }

        // Traverse only if IMPLICIT or TRAVERSE
        if (!($traversalStrategy & (TraversalStrategy::IMPLICIT | TraversalStrategy::TRAVERSE))) {
            return;
        }

        // If IMPLICIT, stop unless we deal with a Traversable
        if ($traversalStrategy & TraversalStrategy::IMPLICIT && !$node->value instanceof \Traversable) {
            return;
        }

        // If TRAVERSE, the constructor will fail if we have no Traversable
        $nodeStack->push(new CollectionNode(
            $node->value,
            $node->propertyPath,
            $node->groups,
            $node->cascadedGroups,
            $traversalStrategy
        ));
    }

    /**
     * Traverses a collection node.
     *
     * At first, each visitor is invoked for this node. Then, unless any
     * of the visitors aborts the traversal by returning false, the successor
     * nodes of the collection node are put on the stack:
     *
     *  - for each object in the collection with associated class metadata, a
     *    new class node is put on the stack;
     *  - if an object has no associated class metadata, but is traversable, and
     *    unless the {@link TraversalStrategy::STOP_RECURSION} flag is set for
     *    collection node, a new collection node is put on the stack for that
     *    object;
     *  - for each array in the collection, a new collection node is put on the
     *    stack.
     *
     * @param CollectionNode            $node      The collection node
     * @param ExecutionContextInterface $context   The current execution context
     * @param \SplStack                 $nodeStack The stack for storing the
     *                                             successor nodes
     *
     * @see ClassNode
     * @see CollectionNode
     */
    private function traverseCollectionNode(CollectionNode $node, ExecutionContextInterface $context, \SplStack $nodeStack)
    {
        // Visitors have two possibilities to influence the traversal:
        //
        // 1. If a visitor's visit() method returns false, the traversal is
        //    skipped entirely.
        // 2. If a visitor's visit() method removes a group from the node,
        //    that group will be skipped in the subtree of that node.

        if (false === $this->visit($node, $context)) {
            return;
        }

        if (0 === count($node->groups)) {
            return;
        }

        $traversalStrategy = $node->traversalStrategy;

        if ($traversalStrategy & TraversalStrategy::STOP_RECURSION) {
            $traversalStrategy = TraversalStrategy::NONE;
        } else {
            $traversalStrategy = TraversalStrategy::IMPLICIT;
        }

        foreach ($node->value as $key => $value) {
            if (is_array($value)) {
                // Arrays are always cascaded, independent of the specified
                // traversal strategy
                // (BC with Symfony < 2.5)
                $nodeStack->push(new CollectionNode(
                    $value,
                    $node->propertyPath.'['.$key.']',
                    $node->groups,
                    null,
                    $traversalStrategy
                ));

                continue;
            }

            // Scalar and null values in the collection are ignored
            // (BC with Symfony < 2.5)
            if (is_object($value)) {
                $this->cascadeObject(
                    $value,
                    $node->propertyPath.'['.$key.']',
                    $node->groups,
                    $traversalStrategy,
                    $nodeStack
                );
            }
        }
    }

    /**
     * Traverses a node that is neither a class nor a collection node.
     *
     * At first, each visitor is invoked for this node. Then, unless any
     * of the visitors aborts the traversal by returning false, the successor
     * nodes of the collection node are put on the stack:
     *
     *  - if the node contains an object with associated class metadata, a new
     *    class node is put on the stack;
     *  - if the node contains a traversable object without associated class
     *    metadata and traversal is enabled according to the selected traversal
     *    strategy, a collection node is put on the stack;
     *  - if the node contains an array, a collection node is put on the stack.
     *
     * @param Node                      $node      The node
     * @param ExecutionContextInterface $context   The current execution context
     * @param \SplStack                 $nodeStack The stack for storing the
     *                                             successor nodes
     */
    private function traverseNode(Node $node, ExecutionContextInterface $context, \SplStack $nodeStack)
    {
        // Visitors have two possibilities to influence the traversal:
        //
        // 1. If a visitor's visit() method returns false, the traversal is
        //    skipped entirely.
        // 2. If a visitor's visit() method removes a group from the node,
        //    that group will be skipped in the subtree of that node.

        if (false === $this->visit($node, $context)) {
            return;
        }

        if (null === $node->value) {
            return;
        }

        // The "cascadedGroups" property is set by the NodeValidationVisitor when
        // traversing group sequences
        $cascadedGroups = null !== $node->cascadedGroups
            ? $node->cascadedGroups
            : $node->groups;

        if (0 === count($cascadedGroups)) {
            return;
        }

        $cascadingStrategy = $node->metadata->getCascadingStrategy();
        $traversalStrategy = $node->traversalStrategy;

        // If no specific traversal strategy was requested when this method
        // was called, use the traversal strategy of the node's metadata
        if ($traversalStrategy & TraversalStrategy::IMPLICIT) {
            // Keep the STOP_RECURSION flag, if it was set
            $traversalStrategy = $node->metadata->getTraversalStrategy()
                | ($traversalStrategy & TraversalStrategy::STOP_RECURSION);
        }

        if (is_array($node->value)) {
            // Arrays are always traversed, independent of the specified
            // traversal strategy
            // (BC with Symfony < 2.5)
            $nodeStack->push(new CollectionNode(
                $node->value,
                $node->propertyPath,
                $cascadedGroups,
                null,
                $traversalStrategy
            ));

            return;
        }

        if ($cascadingStrategy & CascadingStrategy::CASCADE) {
            // If the value is a scalar, pass it anyway, because we want
            // a NoSuchMetadataException to be thrown in that case
            // (BC with Symfony < 2.5)
            $this->cascadeObject(
                $node->value,
                $node->propertyPath,
                $cascadedGroups,
                $traversalStrategy,
                $nodeStack
            );

            return;
        }

        // Currently, the traversal strategy can only be TRAVERSE for a
        // generic node if the cascading strategy is CASCADE. Thus, traversable
        // objects will always be handled within cascadeObject() and there's
        // nothing more to do here.

        // see GenericMetadata::addConstraint()
    }

    /**
     * Executes the cascading logic for an object.
     *
     * If class metadata is available for the object, a class node is put on
     * the node stack. Otherwise, if the selected traversal strategy allows
     * traversal of the object, a new collection node is put on the stack.
     * Otherwise, an exception is thrown.
     *
     * @param object    $object            The object to cascade
     * @param string    $propertyPath      The current property path
     * @param string[]  $groups            The validated groups
     * @param integer   $traversalStrategy The strategy for traversing the
     *                                     cascaded object
     * @param \SplStack $nodeStack         The stack for storing the successor
     *                                     nodes
     *
     * @throws NoSuchMetadataException If the object has no associated metadata
     *                                 and does not implement {@link \Traversable}
     *                                 or if traversal is disabled via the
     *                                 $traversalStrategy argument
     * @throws UnsupportedMetadataException If the metadata returned by the
     *                                      metadata factory does not implement
     *                                      {@link ClassMetadataInterface}
     */
    private function cascadeObject($object, $propertyPath, array $groups, $traversalStrategy, \SplStack $nodeStack)
    {
        try {
            $classMetadata = $this->metadataFactory->getMetadataFor($object);

            if (!$classMetadata instanceof ClassMetadataInterface) {
                throw new UnsupportedMetadataException(sprintf(
                    'The metadata factory should return instances of '.
                    '"Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                    'got: "%s".',
                    is_object($classMetadata) ? get_class($classMetadata) : gettype($classMetadata)
                ));
            }

            $nodeStack->push(new ClassNode(
                $object,
                $classMetadata,
                $propertyPath,
                $groups,
                null,
                $traversalStrategy
            ));
        } catch (NoSuchMetadataException $e) {
            // Rethrow if not Traversable
            if (!$object instanceof \Traversable) {
                throw $e;
            }

            // Rethrow unless IMPLICIT or TRAVERSE
            if (!($traversalStrategy & (TraversalStrategy::IMPLICIT | TraversalStrategy::TRAVERSE))) {
                throw $e;
            }

            $nodeStack->push(new CollectionNode(
                $object,
                $propertyPath,
                $groups,
                null,
                $traversalStrategy
            ));
        }
    }
}
