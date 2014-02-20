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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\CollectionMetadata;
use Symfony\Component\Validator\Mapping\TraversalStrategy;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Node\ClassNode;
use Symfony\Component\Validator\Node\CollectionNode;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\Node\PropertyNode;
use Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NodeTraverser implements NodeTraverserInterface
{
    /**
     * @var NodeVisitorInterface[]
     */
    private $visitors;

    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

    private $traversalStarted = false;

    public function __construct(MetadataFactoryInterface $metadataFactory)
    {
        $this->visitors = new \SplObjectStorage();
        $this->nodeQueue = new \SplQueue();
        $this->metadataFactory = $metadataFactory;
    }

    public function addVisitor(NodeVisitorInterface $visitor)
    {
        $this->visitors->attach($visitor);
    }

    public function removeVisitor(NodeVisitorInterface $visitor)
    {
        $this->visitors->detach($visitor);
    }

    /**
     * {@inheritdoc}
     */
    public function traverse(array $nodes, ExecutionContextInterface $context)
    {
        $isTopLevelCall = !$this->traversalStarted;

        if ($isTopLevelCall) {
            $this->traversalStarted = true;

            foreach ($this->visitors as $visitor) {
                /** @var NodeVisitorInterface $visitor */
                $visitor->beforeTraversal($nodes, $context);
            }
        }

        $traversal = new Traversal($context);

        foreach ($nodes as $node) {
            $traversal->nodeQueue->enqueue($node);

            while (!$traversal->nodeQueue->isEmpty()) {
                $node = $traversal->nodeQueue->dequeue();

                if ($node instanceof ClassNode) {
                    $this->traverseClassNode($node, $traversal);
                } elseif ($node instanceof CollectionNode) {
                    $this->traverseCollectionNode($node, $traversal);
                } else {
                    $this->traverseNode($node, $traversal);
                }
            }
        }

        if ($isTopLevelCall) {
            foreach ($this->visitors as $visitor) {
                /** @var NodeVisitorInterface $visitor */
                $visitor->afterTraversal($nodes, $context);
            }

            $this->traversalStarted = false;
        }
    }

    private function visit(Node $node, ExecutionContextInterface $context)
    {
        foreach ($this->visitors as $visitor) {
            if (false === $visitor->visit($node, $context)) {
                return false;
            }
        }

        return true;
    }

    private function traverseNode(Node $node, Traversal $traversal)
    {
        // Visitors have two possibilities to influence the traversal:
        //
        // 1. If a visitor's enterNode() method returns false, the traversal is
        //    skipped entirely.
        // 2. If a visitor's enterNode() method removes a group from the node,
        //    that group will be skipped in the subtree of that node.

        if (false === $this->visit($node, $traversal->context)) {
            return;
        }

        if (null === $node->value) {
            return;
        }

        // The "cascadedGroups" property is set by the NodeValidatorVisitor when
        // traversing group sequences
        $cascadedGroups = null !== $node->cascadedGroups
            ? $node->cascadedGroups
            : $node->groups;

        if (0 === count($cascadedGroups)) {
            return;
        }

        $cascadingStrategy = $node->metadata->getCascadingStrategy();
        $traversalStrategy = $node->metadata->getTraversalStrategy();

        if (is_array($node->value)) {
            // Arrays are always traversed, independent of the specified
            // traversal strategy
            // (BC with Symfony < 2.5)
            $traversal->nodeQueue->enqueue(new CollectionNode(
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
                $traversal
            );

            return;
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
        $traversal->nodeQueue->enqueue(new CollectionNode(
            $node->value,
            $node->propertyPath,
            $cascadedGroups,
            null,
            $traversalStrategy
        ));
    }

    private function traverseClassNode(ClassNode $node, Traversal $traversal)
    {
        // Visitors have two possibilities to influence the traversal:
        //
        // 1. If a visitor's enterNode() method returns false, the traversal is
        //    skipped entirely.
        // 2. If a visitor's enterNode() method removes a group from the node,
        //    that group will be skipped in the subtree of that node.

        if (false === $this->visit($node, $traversal->context)) {
            return;
        }

        if (0 === count($node->groups)) {
            return;
        }

        foreach ($node->metadata->getConstrainedProperties() as $propertyName) {
            foreach ($node->metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
                $traversal->nodeQueue->enqueue(new PropertyNode(
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
        $traversal->nodeQueue->enqueue(new CollectionNode(
            $node->value,
            $node->propertyPath,
            $node->groups,
            $node->cascadedGroups,
            $traversalStrategy
        ));
    }

    private function traverseCollectionNode(CollectionNode $node, Traversal $traversal)
    {
        // Visitors have two possibilities to influence the traversal:
        //
        // 1. If a visitor's enterNode() method returns false, the traversal is
        //    skipped entirely.
        // 2. If a visitor's enterNode() method removes a group from the node,
        //    that group will be skipped in the subtree of that node.

        if (false === $this->visit($node, $traversal->context)) {
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
                $traversal->nodeQueue->enqueue(new CollectionNode(
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
                    $traversal
                );
            }
        }
    }

    private function cascadeObject($object, $propertyPath, array $groups, $traversalStrategy, Traversal $traversal)
    {
        try {
            $classMetadata = $this->metadataFactory->getMetadataFor($object);

            if (!$classMetadata instanceof ClassMetadataInterface) {
                // error
            }

            $traversal->nodeQueue->enqueue(new ClassNode(
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

            $traversal->nodeQueue->enqueue(new CollectionNode(
                $object,
                $propertyPath,
                $groups,
                null,
                $traversalStrategy
            ));
        }
    }
}
