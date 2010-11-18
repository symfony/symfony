<?php

namespace Symfony\Component\Validator\Mapping;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\GroupDefinitionException;

class ClassMetadata extends ElementMetadata
{
    public $name;
    public $defaultGroup;
    public $members = array();
    public $properties = array();
    public $getters = array();
    public $groupSequence = array();
    private $reflClass;

    /**
     * Constructs a metadata for the given class
     *
     * @param string $class
     */
    public function __construct($class)
    {
        $this->name = $class;
        // class name without namespace
        $this->defaultGroup = substr($class, strrpos($class, '\\') + 1);
    }

    /**
     * Returns the properties to be serialized
     *
     * @return array
     */
    public function __sleep()
    {
        return array_merge(parent::__sleep(), array(
            'getters',
            'groupSequence',
            'members',
            'name',
            'properties',
            'defaultGroup'
        ));
    }

    /**
     * Returns the fully qualified name of the class
     *
     * @return string  The fully qualified class name
     */
    public function getClassName()
    {
        return $this->name;
    }

    /**
     * Returns the name of the default group for this class
     *
     * For each class, the group "Default" is an alias for the group
     * "<ClassName>", where <ClassName> is the non-namespaced name of the
     * class. All constraints implicitely or explicitely assigned to group
     * "Default" belong to both of these groups, unless the class defines
     * a group sequence.
     *
     * If a class defines a group sequence, validating the class in "Default"
     * will validate the group sequence. The constraints assinged to "Default"
     * can still be validated by validating the class in "<ClassName>".
     *
     * @return string  The name of the default group
     */
    public function getDefaultGroup()
    {
        return $this->defaultGroup;
    }

    /**
     * {@inheritDoc}
     */
    public function addConstraint(Constraint $constraint)
    {
        if ($constraint instanceof Valid) {
            throw new ConstraintDefinitionException('The constraint Valid can only be put on properties or getters');
        }

        $constraint->addImplicitGroupName($this->getDefaultGroup());

        parent::addConstraint($constraint);
    }

    /**
     * Adds a constraint to the given property.
     *
     * @param string     $property   The name of the property
     * @param Constraint $constraint The constraint
     *
     * @return ClassMetadata           This object
     */
    public function addPropertyConstraint($property, Constraint $constraint)
    {
        if (!isset($this->properties[$property])) {
            $this->properties[$property] = new PropertyMetadata($this->getClassName(), $property);

            $this->addMemberMetadata($this->properties[$property]);
        }

        $constraint->addImplicitGroupName($this->getDefaultGroup());

        $this->properties[$property]->addConstraint($constraint);

        return $this;
    }

    /**
     * Adds a constraint to the getter of the given property.
     *
     * The name of the getter is assumed to be the name of the property with an
     * uppercased first letter and either the prefix "get" or "is".
     *
     * @param string     $property   The name of the property
     * @param Constraint $constraint The constraint
     *
     * @return ClassMetadata This object
     */
    public function addGetterConstraint($property, Constraint $constraint)
    {
        if (!isset($this->getters[$property])) {
            $this->getters[$property] = new GetterMetadata($this->getClassName(), $property);

            $this->addMemberMetadata($this->getters[$property]);
        }

        $constraint->addImplicitGroupName($this->getDefaultGroup());

        $this->getters[$property]->addConstraint($constraint);

        return $this;
    }

    /**
     * Merges the constraints of the given metadata into this object.
     *
     * @param ClassMetadata $source The source metadata
     */
    public function mergeConstraints(ClassMetadata $source)
    {
        foreach ($source->getConstraints() as $constraint) {
            $this->addConstraint(clone $constraint);
        }

        foreach ($source->getConstrainedProperties() as $property) {
            foreach ($source->getMemberMetadatas($property) as $member) {
                $member = clone $member;

                foreach ($member->getConstraints() as $constraint) {
                    $constraint->addImplicitGroupName($this->getDefaultGroup());
                }

                $this->addMemberMetadata($member);

                if (!$member->isPrivate()) {
                    $property = $member->getPropertyName();

                    if ($member instanceof PropertyMetadata && !isset($this->properties[$property])) {
                        $this->properties[$property] = $member;
                    } else if ($member instanceof GetterMetadata && !isset($this->getters[$property])) {
                        $this->getters[$property] = $member;
                    }
                }
            }
        }
    }

    /**
     * Adds a member metadata
     *
     * @param MemberMetadata $metadata
     */
    protected function addMemberMetadata(MemberMetadata $metadata)
    {
        $property = $metadata->getPropertyName();

        if (!isset($this->members[$property])) {
            $this->members[$property] = array();
        }

        $this->members[$property][] = $metadata;
    }

    /**
     * Returns all metadatas of members describing the given property
     *
     * @param string $property The name of the property
     */
    public function getMemberMetadatas($property)
    {
        return $this->members[$property];
    }

    /**
     * Returns all properties for which constraints are defined.
     *
     * @return array An array of property names
     */
    public function getConstrainedProperties()
    {
        return array_keys($this->members);
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

        if (!in_array($this->getDefaultGroup(), $groups, true)) {
            throw new GroupDefinitionException(sprintf('The group "%s" is missing in the group sequence', $this->getDefaultGroup()));
        }

        $this->groupSequence = $groups;

        return $this;
    }

    /**
     * Returns whether this class has an overridden default group sequence.
     *
     * @return boolean
     */
    public function hasGroupSequence()
    {
        return count($this->groupSequence) > 0;
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
     * Returns a ReflectionClass instance for this class.
     *
     * @return ReflectionClass
     */
    public function getReflectionClass()
    {
        if (!$this->reflClass) {
            $this->reflClass = new \ReflectionClass($this->getClassName());
        }

        return $this->reflClass;
    }
}