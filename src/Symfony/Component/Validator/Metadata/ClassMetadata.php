<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Metadata;

use Metadata\MergeableClassMetadata;
use Metadata\MergeableInterface;
use Metadata\MethodMetadata;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\GroupDefinitionException;

/**
 * This class holds information about constraints that are used for validating.
 * There is two kinds of metadata MethodMetadata (Subclassed as GetterMetadata) and PropertyMetadata
 *
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ClassMetadata extends MergeableClassMetadata
{
    /**
     * @var string
     */
    public $defaultGroup;

    /**
     * @var array
     */
    public $constraints = array();

    /**
     * @var array
     */
    public $groupSequence = array();

    /**
     * Sets the default group to the className without namespace if it is namespaces.
     */
    public function __construct($name)
    {
        parent::__construct($name);

        $this->defaultGroup = current(array_reverse(explode('\\', $name)));
    }

    /**
     * Merges constraints and metadata information into $this from $object
     *
     * @param MergeableInterface $object
     */
    public function merge(MergeableInterface $object)
    {
        if (!$object instanceof ClassMetadata) {
            throw new \InvalidArgumentException('$object must be an instance of ClassMetadata');
        }

        parent::merge($object);

        foreach ($object->getConstraints() as $constraint) {
            $this->addConstraint(clone $constraint);
        }

        foreach ($object->getConstrainedProperties() as $property) {
            foreach ($object->getMemberMetadatas($property) as $member) {
                $member = clone $member;

                foreach ($member->constraints as $constraint) {
                    $constraint->addImplicitGroupName($this->defaultGroup);
                }

                if (!$member->isPrivate()) {
                    $property = $member->getPropertyName();

                    if ($member instanceof PropertyMetadata && !isset($this->propertyMetadata[$property])) {
                        $this->addPropertyMetadata($member);
                    } elseif ($member instanceof GetterMetadata && !isset($this->methodMetadata[$property])) {
                        $this->addMethodMetadata($member);
                    }
                }
            }
        }
    }

    /**
     * Adds a constraint to the getter of the given property.
     *
     * The name of the getter is assumed to be the name of the property with an
     * uppercased first letter and either the prefix "get" or "is".
     *
     * @param string $property
     * @param Constraint $constraint
     * @return ClassMetadata
     */
    public function addGetterConstraint($property, Constraint $constraint)
    {
        if (!isset($this->methodMetadata[$property])) {
            $this->addMethodMetadata(new GetterMetadata($this->name, $property));
        }

        $constraint->addImplicitGroupName($this->defaultGroup);
        $this->methodMetadata[$property]->addConstraint($constraint);

        return $this;
    }

    /**
     * Adds a constraint to the given property.
     *
     * @param string $property
     * @param Constraint $constraint
     * @return ClassMetadata
     */
    public function addPropertyConstraint($property, Constraint $constraint)
    {
        if (!isset($this->propertyMetadata[$property])) {
            $this->addPropertyMetadata(new PropertyMetadata($this->name, $property));
        }

        $constraint->addImplicitGroupName($this->defaultGroup);
        $this->propertyMetadata[$property]->addConstraint($constraint);

        return $this;
    }

    /**
     * Overwrite so only GetterMetadata is allowed.
     *
     * @param MethodMetadata
     * @throws \InvalidArgumentException
     */
    public function addMethodMetadata(MethodMetadata $metadata)
    {
        if (!$metadata instanceof GetterMetadata) {
            throw new \InvalidArgumentException('$metadata must be an instance of GetterMetadata');
        }

        $this->methodMetadata[$metadata->property] = $metadata;
    }

    /**
     * Do we have any validation metadata for properties and/or methods?
     *
     * @param string $property
     */
    public function hasMemberMetadatas($property)
    {
        return (Boolean) isset($this->propertyMetadata[$property]) || isset($this->methodMetadata[$property]);
    }

    /**
     * Get all metadata associated with the given property.
     *
     * @param string $property
     * @return array
     */
    public function getMemberMetadatas($property)
    {
        $propertyMetadata = isset($this->propertyMetadata[$property]) ? $this->propertyMetadata[$property] : null;
        $methodMetadata = isset($this->methodMetadata[$property]) ? $this->methodMetadata[$property] : null;

        return array_filter(array(
            $propertyMetadata,
            $methodMetadata,
        ));
    }

    /**
     * Returns the names of all properties that need validation. methodMetadata keys are also saved as a
     * propertyName
     *
     * @return array
     */
    public function getConstrainedProperties()
    {
        return array_merge(array_keys($this->propertyMetadata), array_keys($this->methodMetadata));
    }

    /**
     * @return array
     */
    public function getConstraints()
    {
        return $this->constraints;
    }

    /**
     * @return Boolean
     */
    public function hasConstraints()
    {
        return (Boolean) $this->constraints;
    }

    /**
     * Adds a constraint
     *
     * @param Constraint $constraint
     */
    public function addConstraint(Constraint $constraint)
    {
        if (!in_array(Constraint::CLASS_CONSTRAINT, (array) $constraint->getTargets())) {
            throw new ConstraintDefinitionException(sprintf('The constraint %s cannot be put on classes', get_class($constraint)));
        }

        $constraint->addImplicitGroupName($this->defaultGroup);

        $this->constraints[] = $constraint;
    }

    /**
     * Returns the constraints which have the given group
     *
     * @param string $group
     * @return array
     */
    public function findConstraints($group)
    {
        return array_filter($this->constraints, function (Constraint $constraint) use ($group) {
            return (Boolean) in_array($group, $constraint->groups);
        });
    }

    /**
     * Sets the default group sequence for this class.
     *
     * @param array $groups An array of group names
     */
    public function setGroupSequence(array $groups)
    {
        if (in_array(Constraint::DEFAULT_GROUP, $groups, true)) {
            throw new GroupDefinitionException(sprintf('The group "%s" is not allowed in group sequences', Constraint::DEFAULT_GROUP));
        }

        if (!in_array($this->defaultGroup, $groups, true)) {
            throw new GroupDefinitionException(sprintf('The group "%s" is missing in the group sequence', $this->defaultGroup));
        }

        $this->groupSequence = $groups;

        return $this;
    }

    /**
     * Returns whether this class has an overridden default group sequence.
     *
     * @return Boolean
     */
    public function hasGroupSequence()
    {
        return (Boolean) $this->groupSequence;
    }

    /**
     * Returns the default group sequence for this class.
     *
     * @return array An array of group names
     */
    public function getGroupSequence()
    {
        return $this->groupSequence;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->name;
    }

    /**
     * Serializes this object into a string
     *
     * @return string
     */
    public function serialize()
    {
        return serialize(array(
            $this->constraints,
            $this->defaultGroup,
            $this->groupSequence,
            parent::serialize()
        ));
    }

    /**
     * Restores the object from a serialized string
     *
     * @param string $str
     */
    public function unserialize($str)
    {
        list(
            $this->constraints,
            $this->defaultGroup,
            $this->groupSequence,
            $parentStr
        ) = unserialize($str);

        parent::unserialize($parentStr);
    }
}

