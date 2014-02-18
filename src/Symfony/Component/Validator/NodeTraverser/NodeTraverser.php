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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
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
    public function traverse(array $nodes)
    {
        $isTopLevelCall = !$this->traversalStarted;

        if ($isTopLevelCall) {
            $this->traversalStarted = true;

            foreach ($this->visitors as $visitor) {
                /** @var NodeVisitorInterface $visitor */
                $visitor->beforeTraversal($nodes);
            }
        }

        foreach ($nodes as $node) {
            if ($node instanceof ClassNode) {
                $this->traverseClassNode($node);
            } else {
                $this->traverseNode($node);
            }
        }

        if ($isTopLevelCall) {
            $this->traversalStarted = false;

            foreach ($this->visitors as $visitor) {
                /** @var NodeVisitorInterface $visitor */
                $visitor->afterTraversal($nodes);
            }
        }
    }

    private function traverseNode(Node $node)
    {
        $stopTraversal = false;

        foreach ($this->visitors as $visitor) {
            if (false === $visitor->enterNode($node)) {
                $stopTraversal = true;
            }
        }

        // Stop the traversal, but execute the leaveNode() methods anyway to
        // perform possible cleanups
        if (!$stopTraversal && null !== $node->value) {
            $cascadingStrategy = $node->metadata->getCascadingStrategy();
            $traversalStrategy = $node->metadata->getTraversalStrategy();

            if (is_array($node->value)) {
                $this->cascadeCollection(
                    $node->value,
                    $node->propertyPath,
                    $node->cascadedGroups,
                    $traversalStrategy
                );
            } elseif ($cascadingStrategy & CascadingStrategy::CASCADE) {
                $this->cascadeObject(
                    $node->value,
                    $node->propertyPath,
                    $node->cascadedGroups,
                    $traversalStrategy
                );
            }
        }

        foreach ($this->visitors as $visitor) {
            $visitor->leaveNode($node);
        }
    }

    private function traverseClassNode(ClassNode $node, $traversalStrategy = TraversalStrategy::IMPLICIT)
    {
        $stopTraversal = false;

        foreach ($this->visitors as $visitor) {
            if (false === $visitor->enterNode($node)) {
                $stopTraversal = true;
            }
        }

        // Stop the traversal, but execute the leaveNode() methods anyway to
        // perform possible cleanups
        if (!$stopTraversal && count($node->groups) > 0) {
            foreach ($node->metadata->getConstrainedProperties() as $propertyName) {
                foreach ($node->metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
                    $this->traverseNode(new PropertyNode(
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

            if ($traversalStrategy & TraversalStrategy::IMPLICIT) {
                $traversalStrategy = $node->metadata->getTraversalStrategy();
            }

            if ($traversalStrategy & TraversalStrategy::TRAVERSE) {
                $this->cascadeCollection(
                    $node->value,
                    $node->propertyPath,
                    $node->groups,
                    $traversalStrategy
                );
            }
        }

        foreach ($this->visitors as $visitor) {
            $visitor->leaveNode($node);
        }
    }

    private function cascadeObject($object, $propertyPath, array $groups, $traversalStrategy)
    {
        try {
            $classMetadata = $this->metadataFactory->getMetadataFor($object);

            $classNode = new ClassNode(
                $object,
                $classMetadata,
                $propertyPath,
                $groups,
                $groups
            );

            $this->traverseClassNode($classNode, $traversalStrategy);
        } catch (NoSuchMetadataException $e) {
            if (!$object instanceof \Traversable || !($traversalStrategy & TraversalStrategy::TRAVERSE)) {
                throw $e;
            }

            // Metadata doesn't necessarily have to exist for
            // traversable objects, because we know how to validate
            // them anyway.
            $this->cascadeCollection(
                 $object,
                 $propertyPath,
                 $groups,
                 $traversalStrategy
            );
        }
    }

    private function cascadeCollection($collection, $propertyPath, array $groups, $traversalStrategy)
    {
        if (!($traversalStrategy & TraversalStrategy::RECURSIVE)) {
            $traversalStrategy = TraversalStrategy::IMPLICIT;
        }

        foreach ($collection as $key => $value) {
            if (is_array($value)) {
                $this->cascadeCollection(
                    $value,
                    $propertyPath.'['.$key.']',
                    $groups,
                    $traversalStrategy
                );

                continue;
            }

            // Scalar and null values in the collection are ignored
            if (is_object($value)) {
                $this->cascadeObject(
                    $value,
                    $propertyPath.'['.$key.']',
                    $groups,
                    $traversalStrategy
                );
            }
        }
    }
}
