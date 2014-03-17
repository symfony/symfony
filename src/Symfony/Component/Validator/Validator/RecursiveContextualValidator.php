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
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Exception\RuntimeException;
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
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;

        if (null !== $constraints) {
            if (!is_array($constraints)) {
                $constraints = array($constraints);
            }

            $metadata = new GenericMetadata();
            $metadata->addConstraints($constraints);

            $this->validateGenericNode(
                $value,
                is_object($value) ? spl_object_hash($value) : null,
                $metadata,
                $this->defaultPropertyPath,
                $groups,
                null,
                TraversalStrategy::IMPLICIT,
                $this->context
            );

            return $this;
        }

        if (is_object($value)) {
            $this->cascadeObject(
                $value,
                $this->defaultPropertyPath,
                $groups,
                TraversalStrategy::IMPLICIT,
                $this->context
            );

            return $this;
        }

        if (is_array($value)) {
            $this->cascadeCollection(
                $value,
                $this->defaultPropertyPath,
                $groups,
                TraversalStrategy::IMPLICIT,
                $this->context
            );

            return $this;
        }

        throw new RuntimeException(sprintf(
            'Cannot validate values of type "%s" automatically. Please '.
            'provide a constraint.',
            gettype($value)
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function validateProperty($container, $propertyName, $groups = null)
    {
        $classMetadata = $this->metadataFactory->getMetadataFor($container);

        if (!$classMetadata instanceof ClassMetadataInterface) {
            // Cannot be UnsupportedMetadataException because of BC with
            // Symfony < 2.5
            throw new ValidatorException(sprintf(
                'The metadata factory should return instances of '.
                '"\Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                'got: "%s".',
                is_object($classMetadata) ? get_class($classMetadata) : gettype($classMetadata)
            ));
        }

        $propertyMetadatas = $classMetadata->getPropertyMetadata($propertyName);
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;
        $cacheKey = spl_object_hash($container);

        foreach ($propertyMetadatas as $propertyMetadata) {
            $propertyValue = $propertyMetadata->getPropertyValue($container);

            $this->validateGenericNode(
                $propertyValue,
                $cacheKey.':'.$propertyName,
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
    public function validatePropertyValue($container, $propertyName, $value, $groups = null)
    {
        $classMetadata = $this->metadataFactory->getMetadataFor($container);

        if (!$classMetadata instanceof ClassMetadataInterface) {
            // Cannot be UnsupportedMetadataException because of BC with
            // Symfony < 2.5
            throw new ValidatorException(sprintf(
                'The metadata factory should return instances of '.
                '"\Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                'got: "%s".',
                is_object($classMetadata) ? get_class($classMetadata) : gettype($classMetadata)
            ));
        }

        $propertyMetadatas = $classMetadata->getPropertyMetadata($propertyName);
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;
        $cacheKey = spl_object_hash($container);

        foreach ($propertyMetadatas as $propertyMetadata) {
            $this->validateGenericNode(
                $value,
                $cacheKey.':'.$propertyName,
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
    private function validateClassNode($value, $cacheKey, ClassMetadataInterface $metadata = null, $propertyPath, array $groups, $cascadedGroups, $traversalStrategy, ExecutionContextInterface $context)
    {
        $context->setNode($value, $metadata, $propertyPath);

        // if group (=[<G1,G2>,G3,G4]) contains group sequence (=<G1,G2>)
        // then call traverse() with each entry of the group sequence and abort
        // if necessary (G1, G2)
        // finally call traverse() with remaining entries ([G3,G4]) or
        // simply continue traversal (if possible)

        foreach ($groups as $key => $group) {
            $cascadedGroup = null;

            // Even if we remove the following clause, the constraints on an
            // object won't be validated again due to the measures taken in
            // validateNodeForGroup().
            // The following shortcut, however, prevents validatedNodeForGroup()
            // from being called at all and enhances performance a bit.

            // Use the object hash for group sequences
            $groupHash = is_object($group) ? spl_object_hash($group) : $group;

            if ($context->isGroupValidated($cacheKey, $groupHash)) {
                // Skip this group when validating the successor nodes
                // (property and/or collection nodes)
                unset($groups[$key]);

                continue;
            }

            $context->markGroupAsValidated($cacheKey, $groupHash);

            // Replace the "Default" group by the group sequence defined
            // for the class, if applicable
            // This is done after checking the cache, so that
            // spl_object_hash() isn't called for this sequence and
            // "Default" is used instead in the cache. This is useful
            // if the getters below return different group sequences in
            // every call.
            if (Constraint::DEFAULT_GROUP === $group) {
                if ($metadata->hasGroupSequence()) {
                    // The group sequence is statically defined for the class
                    $group = $metadata->getGroupSequence();
                    $cascadedGroup = Constraint::DEFAULT_GROUP;
                } elseif ($metadata->isGroupSequenceProvider()) {
                    // The group sequence is dynamically obtained from the validated
                    // object
                    /** @var \Symfony\Component\Validator\GroupSequenceProviderInterface $value */
                    $group = $value->getGroupSequence();
                    $cascadedGroup = Constraint::DEFAULT_GROUP;

                    if (!$group instanceof GroupSequence) {
                        $group = new GroupSequence($group);
                    }
                }
            }

            if ($group instanceof GroupSequence) {
                $this->stepThroughGroupSequence($value, $cacheKey, $metadata, $propertyPath, $traversalStrategy, $group, $cascadedGroup, $context);

                // Skip the group sequence when validating successor nodes
                unset($groups[$key]);

                continue;
            }

            $this->validateInGroup($value, $cacheKey, $metadata, $group, $context);
        }

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

                $propertyValue = $propertyMetadata->getPropertyValue($value);

                $this->validateGenericNode(
                    $propertyValue,
                    $cacheKey.':'.$propertyName,
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
    private function validateGenericNode($value, $cacheKey, MetadataInterface $metadata = null, $propertyPath, array $groups, $cascadedGroups, $traversalStrategy, ExecutionContextInterface $context)
    {
        $context->setNode($value, $metadata, $propertyPath);

        foreach ($groups as $key => $group) {
            if ($group instanceof GroupSequence) {
                $this->stepThroughGroupSequence($value, $cacheKey, $metadata, $propertyPath, $traversalStrategy, $group, null, $context);

                // Skip the group sequence when validating successor nodes
                unset($groups[$key]);

                continue;
            }

            $this->validateInGroup($value, $cacheKey, $metadata, $group, $context);
        }

        if (0 === count($groups)) {
            return;
        }

        if (null === $value) {
            return;
        }

        $cascadingStrategy = $metadata->getCascadingStrategy();

        // Quit unless we have an array or a cascaded object
        if (!is_array($value) && !($cascadingStrategy & CascadingStrategy::CASCADE)) {
            return;
        }

        // If no specific traversal strategy was requested when this method
        // was called, use the traversal strategy of the node's metadata
        if ($traversalStrategy & TraversalStrategy::IMPLICIT) {
            // Keep the STOP_RECURSION flag, if it was set
            $traversalStrategy = $metadata->getTraversalStrategy()
                | ($traversalStrategy & TraversalStrategy::STOP_RECURSION);
        }

        // The "cascadedGroups" property is set by the NodeValidationVisitor when
        // traversing group sequences
        $cascadedGroups = count($cascadedGroups) > 0
            ? $cascadedGroups
            : $groups;

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
     * @param object    $container            The object to cascade
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
    private function cascadeObject($container, $propertyPath, array $groups, $traversalStrategy, ExecutionContextInterface $context)
    {
        try {
            $classMetadata = $this->metadataFactory->getMetadataFor($container);

            if (!$classMetadata instanceof ClassMetadataInterface) {
                throw new UnsupportedMetadataException(sprintf(
                    'The metadata factory should return instances of '.
                    '"Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                    'got: "%s".',
                    is_object($classMetadata) ? get_class($classMetadata) : gettype($classMetadata)
                ));
            }

            $this->validateClassNode(
                $container,
                spl_object_hash($container),
                $classMetadata,
                $propertyPath,
                $groups,
                null,
                $traversalStrategy,
                $context
            );
        } catch (NoSuchMetadataException $e) {
            // Rethrow if not Traversable
            if (!$container instanceof \Traversable) {
                throw $e;
            }

            // Rethrow unless IMPLICIT or TRAVERSE
            if (!($traversalStrategy & (TraversalStrategy::IMPLICIT | TraversalStrategy::TRAVERSE))) {
                throw $e;
            }

            $this->cascadeCollection(
                $container,
                $propertyPath,
                $groups,
                $traversalStrategy,
                $context
            );
        }
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
     * Validates a node's value in each group of a group sequence.
     *
     * If any of the groups' constraints generates a violation, subsequent
     * groups are not validated anymore.
     *
     * @param Node                      $node          The validated node
     * @param GroupSequence             $groupSequence The group sequence
     * @param ExecutionContextInterface $context       The execution context
     */
    private function stepThroughGroupSequence($value, $cacheKey, MetadataInterface $metadata = null, $propertyPath, $traversalStrategy, GroupSequence $groupSequence, $cascadedGroup, ExecutionContextInterface $context)
    {
        $violationCount = count($context->getViolations());
        $cascadedGroups = $cascadedGroup ? array($cascadedGroup) : null;

        foreach ($groupSequence->groups as $groupInSequence) {
            $groups = array($groupInSequence);

            if ($metadata instanceof ClassMetadataInterface) {
                $this->validateClassNode(
                     $value,
                     $cacheKey,
                     $metadata,
                     $propertyPath,
                     $groups,
                     $cascadedGroups,
                     $traversalStrategy,
                     $context
                );
            } else {
                $this->validateGenericNode(
                     $value,
                     $cacheKey,
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
     * @param string                    $containerHash The hash of the node's
     *                                              object (if any)
     *
     * @throws \Exception
     */
    private function validateInGroup($value, $cacheKey, MetadataInterface $metadata, $group, ExecutionContextInterface $context)
    {
        $context->setGroup($group);

        foreach ($metadata->findConstraints($group) as $constraint) {
            // Prevent duplicate validation of constraints, in the case
            // that constraints belong to multiple validated groups
            if (null !== $cacheKey) {
                $constraintHash = spl_object_hash($constraint);

                if ($context->isConstraintValidated($cacheKey, $constraintHash)) {
                    continue;
                }

                $context->markConstraintAsValidated($cacheKey, $constraintHash);
            }

            $validator = $this->validatorFactory->getInstance($constraint);
            $validator->initialize($context);
            $validator->validate($value, $constraint);
        }
    }
}
