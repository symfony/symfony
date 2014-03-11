<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Exception\UnsupportedMetadataException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\GenericMetadata;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Mapping\TraversalStrategy;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Node\ClassNode;
use Symfony\Component\Validator\Node\CollectionNode;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\Node\PropertyNode;
use Symfony\Component\Validator\Util\PropertyPath;

/**
 * Recursive implementation of {@link ContextualValidatorInterface}.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RecursiveContextualValidator implements ContextualValidatorInterface
{
    /**
     * @var ExecutionContextInterface
     */
    private $context;

    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

    /**
     * @var ConstraintValidatorFactoryInterface
     */
    private $validatorFactory;

    /**
     * Creates a validator for the given context.
     *
     * @param ExecutionContextInterface           $context          The execution context
     * @param MetadataFactoryInterface            $metadataFactory  The factory for
     *                                                              fetching the metadata
     *                                                              of validated objects
     * @param ConstraintValidatorFactoryInterface $validatorFactory The factory for creating
     *                                                              constraint validators
     */
    public function __construct(ExecutionContextInterface $context, MetadataFactoryInterface $metadataFactory, ConstraintValidatorFactoryInterface $validatorFactory)
    {
        $this->context = $context;
        $this->defaultPropertyPath = $context->getPropertyPath();
        $this->defaultGroups = array($context->getGroup() ?: Constraint::DEFAULT_GROUP);
        $this->metadataFactory = $metadataFactory;
        $this->validatorFactory = $validatorFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function atPath($path)
    {
        $this->defaultPropertyPath = $this->context->getPropertyPath($path);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, $constraints = null, $groups = null)
    {
        if (null === $constraints) {
            $constraints = array(new Valid());
        } elseif (!is_array($constraints)) {
            $constraints = array($constraints);
        }

        $metadata = new GenericMetadata();
        $metadata->addConstraints($constraints);
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;

        $this->traverseGenericNode(
            $value,
            null,
            $metadata,
            $this->defaultPropertyPath,
            $groups,
            null,
            TraversalStrategy::IMPLICIT,
            $this->context
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validateProperty($object, $propertyName, $groups = null)
    {
        $classMetadata = $this->metadataFactory->getMetadataFor($object);

        if (!$classMetadata instanceof ClassMetadataInterface) {
            throw new ValidatorException(sprintf(
                'The metadata factory should return instances of '.
                '"\Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                'got: "%s".',
                is_object($classMetadata) ? get_class($classMetadata) : gettype($classMetadata)
            ));
        }

        $propertyMetadatas = $classMetadata->getPropertyMetadata($propertyName);
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;

        foreach ($propertyMetadatas as $propertyMetadata) {
            $propertyValue = $propertyMetadata->getPropertyValue($object);

            $this->traverseGenericNode(
                $propertyValue,
                $object,
                $propertyMetadata,
                PropertyPath::append($this->defaultPropertyPath, $propertyName),
                $groups,
                null,
                TraversalStrategy::IMPLICIT,
                $this->context
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        $classMetadata = $this->metadataFactory->getMetadataFor($object);

        if (!$classMetadata instanceof ClassMetadataInterface) {
            throw new ValidatorException(sprintf(
                'The metadata factory should return instances of '.
                '"\Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                'got: "%s".',
                is_object($classMetadata) ? get_class($classMetadata) : gettype($classMetadata)
            ));
        }

        $propertyMetadatas = $classMetadata->getPropertyMetadata($propertyName);
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;

        foreach ($propertyMetadatas as $propertyMetadata) {
            $this->traverseGenericNode(
                $value,
                $object,
                $propertyMetadata,
                PropertyPath::append($this->defaultPropertyPath, $propertyName),
                $groups,
                null,
                TraversalStrategy::IMPLICIT,
                $this->context
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getViolations()
    {
        return $this->context->getViolations();
    }

    /**
     * Normalizes the given group or list of groups to an array.
     *
     * @param mixed $groups The groups to normalize
     *
     * @return array A group array
     */
    protected function normalizeGroups($groups)
    {
        if (is_array($groups)) {
            return $groups;
        }

        return array($groups);
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
     *
     * @throws UnsupportedMetadataException If a property metadata does not
     *                                      implement {@link PropertyMetadataInterface}
     *
     * @see ClassNode
     * @see PropertyNode
     * @see CollectionNode
     * @see TraversalStrategy
     */
    private function traverseClassNode($value, ClassMetadataInterface $metadata = null, $propertyPath, array $groups, $cascadedGroups, $traversalStrategy, ExecutionContextInterface $context)
    {
        $groups = $this->validateNode($value, $value, $metadata, $propertyPath, $groups, $traversalStrategy, $context);

        if (0 === count($groups)) {
            return;
        }

        foreach ($metadata->getConstrainedProperties() as $propertyName) {
            foreach ($metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
                if (!$propertyMetadata instanceof PropertyMetadataInterface) {
                    throw new UnsupportedMetadataException(sprintf(
                        'The property metadata instances should implement '.
                        '"Symfony\Component\Validator\Mapping\PropertyMetadataInterface", '.
                        'got: "%s".',
                        is_object($propertyMetadata) ? get_class($propertyMetadata) : gettype($propertyMetadata)
                    ));
                }

                $this->traverseGenericNode(
                    $propertyMetadata->getPropertyValue($value),
                    $value,
                    $propertyMetadata,
                    $propertyPath
                        ? $propertyPath.'.'.$propertyName
                        : $propertyName,
                    $groups,
                    $cascadedGroups,
                    TraversalStrategy::IMPLICIT,
                    $context
                );
            }
        }

        // If no specific traversal strategy was requested when this method
        // was called, use the traversal strategy of the class' metadata
        if ($traversalStrategy & TraversalStrategy::IMPLICIT) {
            // Keep the STOP_RECURSION flag, if it was set
            $traversalStrategy = $metadata->getTraversalStrategy()
                | ($traversalStrategy & TraversalStrategy::STOP_RECURSION);
        }

        // Traverse only if IMPLICIT or TRAVERSE
        if (!($traversalStrategy & (TraversalStrategy::IMPLICIT | TraversalStrategy::TRAVERSE))) {
            return;
        }

        // If IMPLICIT, stop unless we deal with a Traversable
        if ($traversalStrategy & TraversalStrategy::IMPLICIT && !$value instanceof \Traversable) {
            return;
        }

        // If TRAVERSE, fail if we have no Traversable
        if (!$value instanceof \Traversable) {
            // Must throw a ConstraintDefinitionException for backwards
            // compatibility reasons with Symfony < 2.5
            throw new ConstraintDefinitionException(sprintf(
                'Traversal was enabled for "%s", but this class '.
                'does not implement "\Traversable".',
                get_class($value)
            ));
        }

        $this->cascadeCollection(
            $value,
            $propertyPath,
            $groups,
            $traversalStrategy,
            $context
        );
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
     *
     * @see ClassNode
     * @see CollectionNode
     */
    private function cascadeCollection($collection, $propertyPath, array $groups, $traversalStrategy, ExecutionContextInterface $context)
    {
        if ($traversalStrategy & TraversalStrategy::STOP_RECURSION) {
            $traversalStrategy = TraversalStrategy::NONE;
        } else {
            $traversalStrategy = TraversalStrategy::IMPLICIT;
        }

        foreach ($collection as $key => $value) {
            if (is_array($value)) {
                // Arrays are always cascaded, independent of the specified
                // traversal strategy
                // (BC with Symfony < 2.5)
                $this->cascadeCollection(
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
     */
    private function traverseGenericNode($value, $object, MetadataInterface $metadata = null, $propertyPath, array $groups, $cascadedGroups, $traversalStrategy, ExecutionContextInterface $context)
    {
        $groups = $this->validateNode($value, $object, $metadata, $propertyPath, $groups, $traversalStrategy, $context);

        if (0 === count($groups)) {
            return;
        }

        if (null === $value) {
            return;
        }

        // The "cascadedGroups" property is set by the NodeValidationVisitor when
        // traversing group sequences
        $cascadedGroups = count($cascadedGroups) > 0
            ? $cascadedGroups
            : $groups;

        $cascadingStrategy = $metadata->getCascadingStrategy();

        // If no specific traversal strategy was requested when this method
        // was called, use the traversal strategy of the node's metadata
        if ($traversalStrategy & TraversalStrategy::IMPLICIT) {
            // Keep the STOP_RECURSION flag, if it was set
            $traversalStrategy = $metadata->getTraversalStrategy()
                | ($traversalStrategy & TraversalStrategy::STOP_RECURSION);
        }

        if (is_array($value)) {
            // Arrays are always traversed, independent of the specified
            // traversal strategy
            // (BC with Symfony < 2.5)
            $this->cascadeCollection(
                $value,
                $propertyPath,
                $cascadedGroups,
                $traversalStrategy,
                $context
            );

            return;
        }

        if ($cascadingStrategy & CascadingStrategy::CASCADE) {
            // If the value is a scalar, pass it anyway, because we want
            // a NoSuchMetadataException to be thrown in that case
            // (BC with Symfony < 2.5)
            $this->cascadeObject(
                $value,
                $propertyPath,
                $cascadedGroups,
                $traversalStrategy,
                $context
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
     * @param ExecutionContextInterface $context The current execution context
     *
     * @throws NoSuchMetadataException If the object has no associated metadata
     *                                 and does not implement {@link \Traversable}
     *                                 or if traversal is disabled via the
     *                                 $traversalStrategy argument
     * @throws UnsupportedMetadataException If the metadata returned by the
     *                                      metadata factory does not implement
     *                                      {@link ClassMetadataInterface}
     */
    private function cascadeObject($object, $propertyPath, array $groups, $traversalStrategy, ExecutionContextInterface $context)
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

            $this->traverseClassNode(
                $object,
                $classMetadata,
                $propertyPath,
                $groups,
                null,
                $traversalStrategy,
                $context
            );
        } catch (NoSuchMetadataException $e) {
            // Rethrow if not Traversable
            if (!$object instanceof \Traversable) {
                throw $e;
            }

            // Rethrow unless IMPLICIT or TRAVERSE
            if (!($traversalStrategy & (TraversalStrategy::IMPLICIT | TraversalStrategy::TRAVERSE))) {
                throw $e;
            }

            $this->cascadeCollection(
                $object,
                $propertyPath,
                $groups,
                $traversalStrategy,
                $context
            );
        }
    }

    /**
     * Validates a node's value against the constraints defined in the node's
     * metadata.
     *
     * Objects and constraints that were validated before in the same context
     * will be skipped.
     *
     * @param Node                      $node    The current node
     * @param ExecutionContextInterface $context The execution context
     *
     * @return array The groups in which the successor nodes should be validated
     */
    public function validateNode($value, $object, MetadataInterface $metadata = null, $propertyPath, array $groups, $traversalStrategy, ExecutionContextInterface $context)
    {
        $context->setValue($value);
        $context->setMetadata($metadata);
        $context->setPropertyPath($propertyPath);

        if ($metadata instanceof ClassMetadataInterface) {
            $groups = $this->replaceDefaultGroup($value, $metadata, $groups);
        }

        $objectHash = is_object($object) ? spl_object_hash($object) : null;

        // if group (=[<G1,G2>,G3,G4]) contains group sequence (=<G1,G2>)
        // then call traverse() with each entry of the group sequence and abort
        // if necessary (G1, G2)
        // finally call traverse() with remaining entries ([G3,G4]) or
        // simply continue traversal (if possible)

        foreach ($groups as $key => $group) {
            // Even if we remove the following clause, the constraints on an
            // object won't be validated again due to the measures taken in
            // validateNodeForGroup().
            // The following shortcut, however, prevents validatedNodeForGroup()
            // from being called at all and enhances performance a bit.
            if ($metadata instanceof ClassMetadataInterface) {
                // Use the object hash for group sequences
                $groupHash = is_object($group) ? spl_object_hash($group) : $group;

                if ($context->isObjectValidatedForGroup($objectHash, $groupHash)) {
                    // Skip this group when validating the successor nodes
                    // (property and/or collection nodes)
                    unset($groups[$key]);

                    continue;
                }

                $context->markObjectAsValidatedForGroup($objectHash, $groupHash);
            }

            // Validate normal group
            if (!$group instanceof GroupSequence) {
                $this->validateNodeForGroup($value, $objectHash, $metadata, $group, $context);

                continue;
            }

            // Traverse group sequence until a violation is generated
            $this->stepThroughGroupSequence($value, $object, $metadata, $propertyPath, $traversalStrategy, $group, $context);

            // Skip the group sequence when validating successor nodes
            unset($groups[$key]);
        }

        return $groups;
    }

    /**
     * Validates a node's value in each group of a group sequence.
     *
     * If any of the groups' constraints generates a violation, subsequent
     * groups are not validated anymore.
     *
     * @param Node                      $node          The validated node
     * @param GroupSequence             $groupSequence The group sequence
     * @param ExecutionContextInterface $context       The execution context
     */
    private function stepThroughGroupSequence($value, $object, MetadataInterface $metadata = null, $propertyPath, $traversalStrategy, GroupSequence $groupSequence, ExecutionContextInterface $context)
    {
        $violationCount = count($context->getViolations());

        foreach ($groupSequence->groups as $groupInSequence) {
            $groups = array($groupInSequence);
            $cascadedGroups = null;

            if (null !== $groupSequence->cascadedGroup) {
                $cascadedGroups = array($groupSequence->cascadedGroup);
            }

            if ($metadata instanceof ClassMetadataInterface) {
                $this->traverseClassNode(
                     $value,
                     $metadata,
                     $propertyPath,
                     $groups,
                     $cascadedGroups,
                     $traversalStrategy,
                     $context
                );
            } else {
                $this->traverseGenericNode(
                     $value,
                     $object,
                     $metadata,
                     $propertyPath,
                     $groups,
                     $cascadedGroups,
                     $traversalStrategy,
                     $context
                );
            }

            // Abort sequence validation if a violation was generated
            if (count($context->getViolations()) > $violationCount) {
                break;
            }
        }
    }

    /**
     * Validates a node's value against all constraints in the given group.
     *
     * @param Node                      $node       The validated node
     * @param string                    $group      The group to validate
     * @param ExecutionContextInterface $context    The execution context
     * @param string                    $objectHash The hash of the node's
     *                                              object (if any)
     *
     * @throws \Exception
     */
    private function validateNodeForGroup($value, $objectHash, MetadataInterface $metadata = null, $group, ExecutionContextInterface $context)
    {
        try {
            $context->setGroup($group);

            foreach ($metadata->findConstraints($group) as $constraint) {
                // Prevent duplicate validation of constraints, in the case
                // that constraints belong to multiple validated groups
                if (null !== $objectHash) {
                    $constraintHash = spl_object_hash($constraint);

                    if ($metadata instanceof ClassMetadataInterface) {
                        if ($context->isClassConstraintValidated($objectHash, $constraintHash)) {
                            continue;
                        }

                        $context->markClassConstraintAsValidated($objectHash, $constraintHash);
                    } elseif ($metadata instanceof PropertyMetadataInterface) {
                        $propertyName = $metadata->getPropertyName();

                        if ($context->isPropertyConstraintValidated($objectHash, $propertyName, $constraintHash)) {
                            continue;
                        }

                        $context->markPropertyConstraintAsValidated($objectHash, $propertyName, $constraintHash);
                    }
                }

                $validator = $this->validatorFactory->getInstance($constraint);
                $validator->initialize($context);
                $validator->validate($value, $constraint);
            }

            $context->setGroup(null);
        } catch (\Exception $e) {
            // Should be put into a finally block once we switch to PHP 5.5
            $context->setGroup(null);

            throw $e;
        }
    }

    /**
     * @param                   $value
     * @param ClassMetadataInterface $metadata
     * @param array             $groups
     *
     * @return array
     */
    private function replaceDefaultGroup($value, ClassMetadataInterface $metadata, array $groups)
    {
        $groupSequence = null;

        if ($metadata->hasGroupSequence()) {
            // The group sequence is statically defined for the class
            $groupSequence = $metadata->getGroupSequence();
        } elseif ($metadata->isGroupSequenceProvider()) {
            // The group sequence is dynamically obtained from the validated
            // object
            /** @var \Symfony\Component\Validator\GroupSequenceProviderInterface $value */
            $groupSequence = $value->getGroupSequence();

            if (!$groupSequence instanceof GroupSequence) {
                $groupSequence = new GroupSequence($groupSequence);
            }
        }

        if (null !== $groupSequence) {
            $key = array_search(Constraint::DEFAULT_GROUP, $groups);

            if (false !== $key) {
                // Replace the "Default" group by the group sequence
                $groups[$key] = $groupSequence;

                // Cascade the "Default" group when validating the sequence
                $groupSequence->cascadedGroup = Constraint::DEFAULT_GROUP;
            }
        }

        return $groups;
    }
}
