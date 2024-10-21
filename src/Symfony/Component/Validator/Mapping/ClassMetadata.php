<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Cascade;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\GroupDefinitionException;

/**
 * Default implementation of {@link ClassMetadataInterface}.
 *
 * This class supports serialization and cloning.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ClassMetadata extends GenericMetadata implements ClassMetadataInterface
{
    /**
     * @var string
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getClassName()} instead.
     */
    public $name;

    /**
     * @var string
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getDefaultGroup()} instead.
     */
    public $defaultGroup;

    /**
     * @var MemberMetadata[][]
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getPropertyMetadata()} instead.
     */
    public $members = [];

    /**
     * @var PropertyMetadata[]
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getPropertyMetadata()} instead.
     */
    public $properties = [];

    /**
     * @var GetterMetadata[]
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getPropertyMetadata()} instead.
     */
    public $getters = [];

    /**
     * @var array
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getGroupSequence()} instead.
     */
    public $groupSequence = [];

    /**
     * @var bool
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link isGroupSequenceProvider()} instead.
     */
    public $groupSequenceProvider = false;

    /**
     * The strategy for traversing traversable objects.
     *
     * By default, only instances of {@link \Traversable} are traversed.
     *
     * @var int
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getTraversalStrategy()} instead.
     */
    public $traversalStrategy = TraversalStrategy::IMPLICIT;

    /**
     * @var \ReflectionClass
     */
    private $reflClass;

    public function __construct(string $class)
    {
        $this->name = $class;
        // class name without namespace
        if (false !== $nsSep = strrpos($class, '\\')) {
            $this->defaultGroup = substr($class, $nsSep + 1);
        } else {
            $this->defaultGroup = $class;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __sleep()
    {
        $parentProperties = parent::__sleep();

        // Don't store the cascading strategy. Classes never cascade.
        unset($parentProperties[array_search('cascadingStrategy', $parentProperties)]);

        return array_merge($parentProperties, [
            'getters',
            'groupSequence',
            'groupSequenceProvider',
            'members',
            'name',
            'properties',
            'defaultGroup',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getClassName()
    {
        return $this->name;
    }

    /**
     * Returns the name of the default group for this class.
     *
     * For each class, the group "Default" is an alias for the group
     * "<ClassName>", where <ClassName> is the non-namespaced name of the
     * class. All constraints implicitly or explicitly assigned to group
     * "Default" belong to both of these groups, unless the class defines
     * a group sequence.
     *
     * If a class defines a group sequence, validating the class in "Default"
     * will validate the group sequence. The constraints assigned to "Default"
     * can still be validated by validating the class in "<ClassName>".
     *
     * @return string
     */
    public function getDefaultGroup()
    {
        return $this->defaultGroup;
    }

    /**
     * {@inheritdoc}
     *
     * If the constraint {@link Cascade} is added, the cascading strategy will be
     * changed to {@link CascadingStrategy::CASCADE}.
     *
     * If the constraint {@link Traverse} is added, the traversal strategy will be
     * changed. Depending on the $traverse property of that constraint,
     * the traversal strategy will be set to one of the following:
     *
     *  - {@link TraversalStrategy::IMPLICIT} by default
     *  - {@link TraversalStrategy::NONE} if $traverse is disabled
     *  - {@link TraversalStrategy::TRAVERSE} if $traverse is enabled
     */
    public function addConstraint(Constraint $constraint)
    {
        $this->checkConstraint($constraint);

        if ($constraint instanceof Traverse) {
            if ($constraint->traverse) {
                // If traverse is true, traversal should be explicitly enabled
                $this->traversalStrategy = TraversalStrategy::TRAVERSE;
            } else {
                // If traverse is false, traversal should be explicitly disabled
                $this->traversalStrategy = TraversalStrategy::NONE;
            }

            // The constraint is not added
            return $this;
        }

        if ($constraint instanceof Cascade) {
            if (\PHP_VERSION_ID < 70400) {
                throw new ConstraintDefinitionException(sprintf('The constraint "%s" requires PHP 7.4.', Cascade::class));
            }

            $this->cascadingStrategy = CascadingStrategy::CASCADE;

            foreach ($this->getReflectionClass()->getProperties() as $property) {
                if ($this->canCascade($property->getType())) {
                    $this->addPropertyConstraint($property->getName(), new Valid());
                }
            }

            // The constraint is not added
            return $this;
        }

        $constraint->addImplicitGroupName($this->getDefaultGroup());

        parent::addConstraint($constraint);

        return $this;
    }

    /**
     * Adds a constraint to the given property.
     *
     * @return $this
     */
    public function addPropertyConstraint(string $property, Constraint $constraint)
    {
        if (!isset($this->properties[$property])) {
            $this->properties[$property] = new PropertyMetadata($this->getClassName(), $property);

            $this->addPropertyMetadata($this->properties[$property]);
        }

        $constraint->addImplicitGroupName($this->getDefaultGroup());

        $this->properties[$property]->addConstraint($constraint);

        return $this;
    }

    /**
     * @param Constraint[] $constraints
     *
     * @return $this
     */
    public function addPropertyConstraints(string $property, array $constraints)
    {
        foreach ($constraints as $constraint) {
            $this->addPropertyConstraint($property, $constraint);
        }

        return $this;
    }

    /**
     * Adds a constraint to the getter of the given property.
     *
     * The name of the getter is assumed to be the name of the property with an
     * uppercased first letter and the prefix "get", "is" or "has".
     *
     * @return $this
     */
    public function addGetterConstraint(string $property, Constraint $constraint)
    {
        if (!isset($this->getters[$property])) {
            $this->getters[$property] = new GetterMetadata($this->getClassName(), $property);

            $this->addPropertyMetadata($this->getters[$property]);
        }

        $constraint->addImplicitGroupName($this->getDefaultGroup());

        $this->getters[$property]->addConstraint($constraint);

        return $this;
    }

    /**
     * Adds a constraint to the getter of the given property.
     *
     * @return $this
     */
    public function addGetterMethodConstraint(string $property, string $method, Constraint $constraint)
    {
        if (!isset($this->getters[$property])) {
            $this->getters[$property] = new GetterMetadata($this->getClassName(), $property, $method);

            $this->addPropertyMetadata($this->getters[$property]);
        }

        $constraint->addImplicitGroupName($this->getDefaultGroup());

        $this->getters[$property]->addConstraint($constraint);

        return $this;
    }

    /**
     * @param Constraint[] $constraints
     *
     * @return $this
     */
    public function addGetterConstraints(string $property, array $constraints)
    {
        foreach ($constraints as $constraint) {
            $this->addGetterConstraint($property, $constraint);
        }

        return $this;
    }

    /**
     * @param Constraint[] $constraints
     *
     * @return $this
     */
    public function addGetterMethodConstraints(string $property, string $method, array $constraints)
    {
        foreach ($constraints as $constraint) {
            $this->addGetterMethodConstraint($property, $method, $constraint);
        }

        return $this;
    }

    /**
     * Merges the constraints of the given metadata into this object.
     */
    public function mergeConstraints(self $source)
    {
        if ($source->isGroupSequenceProvider()) {
            $this->setGroupSequenceProvider(true);
        }

        foreach ($source->getConstraints() as $constraint) {
            $this->addConstraint(clone $constraint);
        }

        foreach ($source->getConstrainedProperties() as $property) {
            foreach ($source->getPropertyMetadata($property) as $member) {
                $member = clone $member;

                foreach ($member->getConstraints() as $constraint) {
                    if (\in_array($constraint::DEFAULT_GROUP, $constraint->groups, true)) {
                        $member->constraintsByGroup[$this->getDefaultGroup()][] = $constraint;
                    }

                    $constraint->addImplicitGroupName($this->getDefaultGroup());
                }

                if ($member instanceof MemberMetadata && !$member->isPrivate($this->name)) {
                    $property = $member->getPropertyName();
                    $this->members[$property][] = $member;

                    if ($member instanceof PropertyMetadata && !isset($this->properties[$property])) {
                        $this->properties[$property] = $member;
                    } elseif ($member instanceof GetterMetadata && !isset($this->getters[$property])) {
                        $this->getters[$property] = $member;
                    }
                } else {
                    $this->addPropertyMetadata($member);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasPropertyMetadata(string $property)
    {
        return \array_key_exists($property, $this->members);
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyMetadata(string $property)
    {
        return $this->members[$property] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function getConstrainedProperties()
    {
        return array_keys($this->members);
    }

    /**
     * Sets the default group sequence for this class.
     *
     * @param string[]|GroupSequence $groupSequence An array of group names
     *
     * @return $this
     *
     * @throws GroupDefinitionException
     */
    public function setGroupSequence($groupSequence)
    {
        if ($this->isGroupSequenceProvider()) {
            throw new GroupDefinitionException('Defining a static group sequence is not allowed with a group sequence provider.');
        }

        if (\is_array($groupSequence)) {
            $groupSequence = new GroupSequence($groupSequence);
        }

        if (\in_array(Constraint::DEFAULT_GROUP, $groupSequence->groups, true)) {
            throw new GroupDefinitionException(sprintf('The group "%s" is not allowed in group sequences.', Constraint::DEFAULT_GROUP));
        }

        if (!\in_array($this->getDefaultGroup(), $groupSequence->groups, true)) {
            throw new GroupDefinitionException(sprintf('The group "%s" is missing in the group sequence.', $this->getDefaultGroup()));
        }

        $this->groupSequence = $groupSequence;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasGroupSequence()
    {
        return $this->groupSequence && \count($this->groupSequence->groups) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupSequence()
    {
        return $this->groupSequence;
    }

    /**
     * Returns a ReflectionClass instance for this class.
     *
     * @return \ReflectionClass
     */
    public function getReflectionClass()
    {
        if (!$this->reflClass) {
            $this->reflClass = new \ReflectionClass($this->getClassName());
        }

        return $this->reflClass;
    }

    /**
     * Sets whether a group sequence provider should be used.
     *
     * @throws GroupDefinitionException
     */
    public function setGroupSequenceProvider(bool $active)
    {
        if ($this->hasGroupSequence()) {
            throw new GroupDefinitionException('Defining a group sequence provider is not allowed with a static group sequence.');
        }

        if (!$this->getReflectionClass()->implementsInterface('Symfony\Component\Validator\GroupSequenceProviderInterface')) {
            throw new GroupDefinitionException(sprintf('Class "%s" must implement GroupSequenceProviderInterface.', $this->name));
        }

        $this->groupSequenceProvider = $active;
    }

    /**
     * {@inheritdoc}
     */
    public function isGroupSequenceProvider()
    {
        return $this->groupSequenceProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function getCascadingStrategy()
    {
        return $this->cascadingStrategy;
    }

    private function addPropertyMetadata(PropertyMetadataInterface $metadata)
    {
        $property = $metadata->getPropertyName();

        $this->members[$property][] = $metadata;
    }

    private function checkConstraint(Constraint $constraint)
    {
        if (!\in_array(Constraint::CLASS_CONSTRAINT, (array) $constraint->getTargets(), true)) {
            throw new ConstraintDefinitionException(sprintf('The constraint "%s" cannot be put on classes.', get_debug_type($constraint)));
        }

        if ($constraint instanceof Composite) {
            foreach ($constraint->getNestedConstraints() as $nestedConstraint) {
                $this->checkConstraint($nestedConstraint);
            }
        }
    }

    private function canCascade(?\ReflectionType $type = null): bool
    {
        if (null === $type) {
            return false;
        }

        if ($type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $nestedType) {
                if ($this->canCascade($nestedType)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $nestedType) {
                if (!$this->canCascade($nestedType)) {
                    return false;
                }
            }

            return true;
        }

        return $type instanceof \ReflectionNamedType && (\in_array($type->getName(), ['array', 'null'], true) || class_exists($type->getName()));
    }
}
