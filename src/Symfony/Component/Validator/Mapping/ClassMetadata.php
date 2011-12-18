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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\GroupDefinitionException;

/**
 * Represents all the configured constraints on a given class.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
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
        if (false !== $nsSep = strrpos($class, '\\')) {
            $this->defaultGroup = substr($class, $nsSep + 1);
        } else {
            $this->defaultGroup = $class;
        }
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
     * class. All constraints implicitly or explicitly assigned to group
     * "Default" belong to both of these groups, unless the class defines
     * a group sequence.
     *
     * If a class defines a group sequence, validating the class in "Default"
     * will validate the group sequence. The constraints assigned to "Default"
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
        if (!in_array(Constraint::CLASS_CONSTRAINT, (array) $constraint->getTargets())) {
            throw new ConstraintDefinitionException(sprintf(
                'The constraint %s cannot be put on classes',
                get_class($constraint)
            ));
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
                    } elseif ($member instanceof GetterMetadata && !isset($this->getters[$property])) {
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

        $this->members[$property][] = $metadata;
    }

    /**
     * Returns true if metadatas of members is present for the given property.
     *
     * @param string $property The name of the property
     *
     * @return Boolean
     */
    public function hasMemberMetadatas($property)
    {
        return array_key_exists($property, $this->members);
    }

    /**
     * Returns all metadatas of members describing the given property
     *
     * @param string $property The name of the property
     *
     * @return array An array of MemberMetadata
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
     * @return Boolean
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
