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
use Symfony\Component\Validator\Mapping\TraversalStrategy;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Node\ClassNode;
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

        foreach ($nodes as $node) {
            if ($node instanceof ClassNode) {
                $this->traverseClassNode($node, $context);
            } else {
                $this->traverseNode($node, $context);
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

    private function enterNode(Node $node, ExecutionContextInterface $context)
    {
        $continueTraversal = true;

        foreach ($this->visitors as $visitor) {
            if (false === $visitor->enterNode($node, $context)) {
                $continueTraversal = false;

                // Continue, so that the enterNode() method of all visitors
                // is called
            }
        }

        return $continueTraversal;
    }

    private function leaveNode(Node $node, ExecutionContextInterface $context)
    {
        foreach ($this->visitors as $visitor) {
            $visitor->leaveNode($node, $context);
        }
    }

    private function traverseNode(Node $node, ExecutionContextInterface $context)
    {
        $continue = $this->enterNode($node, $context);

        // Visitors have two possibilities to influence the traversal:
        //
        // 1. If a visitor's enterNode() method returns false, the traversal is
        //    skipped entirely.
        // 2. If a visitor's enterNode() method removes a group from the node,
        //    that group will be skipped in the subtree of that node.

        if (false === $continue) {
            $this->leaveNode($node, $context);

            return;
        }

        if (null === $node->value) {
            $this->leaveNode($node, $context);

            return;
        }

        // The "cascadedGroups" property is set by the NodeValidator when
        // traversing group sequences
        $cascadedGroups = null !== $node->cascadedGroups
            ? $node->cascadedGroups
            : $node->groups;

        if (0 === count($cascadedGroups)) {
            $this->leaveNode($node, $context);

            return;
        }

        $cascadingStrategy = $node->metadata->getCascadingStrategy();
        $traversalStrategy = $node->metadata->getTraversalStrategy();

        if (is_array($node->value)) {
            // Arrays are always traversed, independent of the specified
            // traversal strategy
            // (BC with Symfony < 2.5)
            $this->cascadeEachObjectIn(
                $node->value,
                $node->propertyPath,
                $cascadedGroups,
                $traversalStrategy,
                $context
            );

            $this->leaveNode($node, $context);

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
                $context
            );

            $this->leaveNode($node, $context);

            return;
        }

        // Traverse only if the TRAVERSE bit is set
        if (!($traversalStrategy & TraversalStrategy::TRAVERSE)) {
            $this->leaveNode($node, $context);

            return;
        }

        if (!$node->value instanceof \Traversable) {
            if ($traversalStrategy & TraversalStrategy::IGNORE_NON_TRAVERSABLE) {
                $this->leaveNode($node, $context);

                return;
            }

            throw new ConstraintDefinitionException(sprintf(
                'Traversal was enabled for "%s", but this class '.
                'does not implement "\Traversable".',
                get_class($node->value)
            ));
        }

        $this->cascadeEachObjectIn(
            $node->value,
            $node->propertyPath,
            $cascadedGroups,
            $traversalStrategy,
            $context
        );

        $this->leaveNode($node, $context);
    }

    private function traverseClassNode(ClassNode $node, ExecutionContextInterface $context, $traversalStrategy = TraversalStrategy::IMPLICIT)
    {
        $continue = $this->enterNode($node, $context);

        // Visitors have two possibilities to influence the traversal:
        //
        // 1. If a visitor's enterNode() method returns false, the traversal is
        //    skipped entirely.
        // 2. If a visitor's enterNode() method removes a group from the node,
        //    that group will be skipped in the subtree of that node.

        if (false === $continue) {
            $this->leaveNode($node, $context);

            return;
        }

        if (0 === count($node->groups)) {
            $this->leaveNode($node, $context);

            return;
        }

        foreach ($node->metadata->getConstrainedProperties() as $propertyName) {
            foreach ($node->metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
                $propertyNode = new PropertyNode(
                    $propertyMetadata->getPropertyValue($node->value),
                    $propertyMetadata,
                    $node->propertyPath
                        ? $node->propertyPath.'.'.$propertyName
                        : $propertyName,
                    $node->groups,
                    $node->cascadedGroups
                );

                $this->traverseNode($propertyNode, $context);
            }
        }

        // If no specific traversal strategy was requested when this method
        // was called, use the traversal strategy of the class' metadata
        if (TraversalStrategy::IMPLICIT === $traversalStrategy) {
            $traversalStrategy = $node->metadata->getTraversalStrategy();
        }

        // Traverse only if the TRAVERSE bit is set
        if (!($traversalStrategy & TraversalStrategy::TRAVERSE)) {
            $this->leaveNode($node, $context);

            return;
        }

        if (!$node->value instanceof \Traversable) {
            if ($traversalStrategy & TraversalStrategy::IGNORE_NON_TRAVERSABLE) {
                $this->leaveNode($node, $context);

                return;
            }

            throw new ConstraintDefinitionException(sprintf(
                'Traversal was enabled for "%s", but this class '.
                'does not implement "\Traversable".',
                get_class($node->value)
            ));
        }

        $this->cascadeEachObjectIn(
            $node->value,
            $node->propertyPath,
            $node->groups,
            $traversalStrategy,
            $context
        );

        $this->leaveNode($node, $context);
    }

    private function cascadeObject($object, $propertyPath, array $groups, $traversalStrategy, ExecutionContextInterface $context)
    {
        try {
            $classMetadata = $this->metadataFactory->getMetadataFor($object);

            if (!$classMetadata instanceof ClassMetadataInterface) {
                // error
            }

            $classNode = new ClassNode(
                $object,
                $classMetadata,
                $propertyPath,
                $groups
            );

            $this->traverseClassNode($classNode, $context, $traversalStrategy);
        } catch (NoSuchMetadataException $e) {
            // Rethrow if the TRAVERSE bit is not set
            if (!($traversalStrategy & TraversalStrategy::TRAVERSE)) {
                throw $e;
            }

            // Rethrow if the object does not implement Traversable
            if (!$object instanceof \Traversable) {
                throw $e;
            }

            // In that case, iterate the object and cascade each entry
            $this->cascadeEachObjectIn(
                 $object,
                 $propertyPath,
                 $groups,
                 $traversalStrategy,
                 $context
            );
        }
    }

    private function cascadeEachObjectIn($collection, $propertyPath, array $groups, $traversalStrategy, ExecutionContextInterface $context)
    {
        if ($traversalStrategy & TraversalStrategy::RECURSIVE) {
            // Try to traverse nested objects, but ignore if they do not
            // implement Traversable
            $traversalStrategy |= TraversalStrategy::IGNORE_NON_TRAVERSABLE;
        } else {
            // If the RECURSIVE bit is not set, change the strategy to IMPLICIT
            // in order to respect the metadata's traversal strategy of each entry
            // in the collection
            $traversalStrategy = TraversalStrategy::IMPLICIT;
        }

        foreach ($collection as $key => $value) {
            if (is_array($value)) {
                // Arrays are always cascaded, independent of the specified
                // traversal strategy
                // (BC with Symfony < 2.5)
                $this->cascadeEachObjectIn(
                    $value,
                    $propertyPath.'['.$key.']',
                    $groups,
                    $traversalStrategy,
                    $context
                );

                continue;
            }

            // Scalar and null values in the collection are ignored
            // (BC with Symfony < 2.5)
            if (is_object($value)) {
                $this->cascadeObject(
                    $value,
                    $propertyPath.'['.$key.']',
                    $groups,
                    $traversalStrategy,
                    $context
                );
            }
        }
    }
}
