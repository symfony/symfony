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

use Symfony\Component\Validator\ValidationVisitorInterface;
use Symfony\Component\Validator\PropertyMetadataContainerInterface;
use Symfony\Component\Validator\ClassBasedInterface;
use Symfony\Component\Validator\MetadataInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\GroupDefinitionException;

/**
 * Represents all the configured constraints on a given class.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ClassMetadata extends ElementMetadata implements MetadataInterface, ClassBasedInterface, PropertyMetadataContainerInterface
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $defaultGroup;

    /**
     * @var MemberMetadata[]
     */
    public $members = array();

    /**
     * @var PropertyMetadata[]
     */
    public $properties = array();

    /**
     * @var GetterMetadata[]
     */
    public $getters = array();

    /**
     * @var array
     */
    public $groupSequence = array();

    /**
     * @var bool
     */
    public $groupSequenceProvider = false;

    /**
     * @var \ReflectionClass
     */
    private $reflClass;

    /**
     * Constructs a metadata for the given class.
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

    public function accept(ValidationVisitorInterface $visitor, $value, $group, $propertyPath, $propagatedGroup = null)
    {
        if (null === $propagatedGroup && Constraint::DEFAULT_GROUP === $group
                && ($this->hasGroupSequence() || $this->isGroupSequenceProvider())) {
            if ($this->hasGroupSequence()) {
                $groups = $this->getGroupSequence();
            } else {
                $groups = $value->getGroupSequence();
            }

            foreach ($groups as $group) {
                $this->accept($visitor, $value, $group, $propertyPath, ($this->defaultGroup === $group) ? Constraint::DEFAULT_GROUP : null);

                if (count($visitor->getViolations()) > 0) {
                    break;
                }
            }

            return;
        }

        $visitor->visit($this, $value, $group, $propertyPath);

        if (null !== $value) {
            $pathPrefix = empty($propertyPath) ? '' : $propertyPath.'.';

            foreach ($this->getConstrainedProperties() as $property) {
                foreach ($this->getMemberMetadatas($property) as $member) {
                    $member->accept($visitor, $member->getPropertyValue($value), $group, $pathPrefix.$property, $propagatedGroup);
                }
            }
        }
    }

    /**
     * Returns the properties to be serialized.
     *
     * @return array
     */
    public function __sleep()
    {
        return array_merge(parent::__sleep(), array(
            'getters',
            'groupSequence',
            'groupSequenceProvider',
            'members',
            'name',
            'properties',
            'defaultGroup',
        ));
    }

    /**
     * Returns the fully qualified name of the class.
     *
     * @return string The fully qualified class name
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
     * @return string The name of the default group
     */
    public function getDefaultGroup()
    {
        return $this->defaultGroup;
    }

    /**
     * {@inheritdoc}
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
     * @return ClassMetadata This object
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

                if (!$member->isPrivate($this->name)) {
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
     * Adds a member metadata.
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
     * @return bool
     */
    public function hasMemberMetadatas($property)
    {
        return array_key_exists($property, $this->members);
    }

    /**
     * Returns all metadatas of members describing the given property.
     *
     * @param string $property The name of the property
     *
     * @return MemberMetadata[] An array of MemberMetadata
     */
    public function getMemberMetadatas($property)
    {
        if (!isset($this->members[$property])) {
            return array();
        }

        return $this->members[$property];
    }

    /**
     * {@inheritdoc}
     */
    public function hasPropertyMetadata($property)
    {
        return array_key_exists($property, $this->members);
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyMetadata($property)
    {
        if (!isset($this->members[$property])) {
            return array();
        }

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
     *
     * @return ClassMetadata
     *
     * @throws GroupDefinitionException
     */
    public function setGroupSequence(array $groups)
    {
        if ($this->isGroupSequenceProvider()) {
            throw new GroupDefinitionException('Defining a static group sequence is not allowed with a group sequence provider');
        }

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
     * @return bool
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
     * @param bool $active
     *
     * @throws GroupDefinitionException
     */
    public function setGroupSequenceProvider($active)
    {
        if ($this->hasGroupSequence()) {
            throw new GroupDefinitionException('Defining a group sequence provider is not allowed with a static group sequence');
        }

        if (!$this->getReflectionClass()->implementsInterface('Symfony\Component\Validator\GroupSequenceProviderInterface')) {
            throw new GroupDefinitionException(sprintf('Class "%s" must implement GroupSequenceProviderInterface', $this->name));
        }

        $this->groupSequenceProvider = $active;
    }

    /**
     * Returns whether the class is a group sequence provider.
     *
     * @return bool
     */
    public function isGroupSequenceProvider()
    {
        return $this->groupSequenceProvider;
    }
}
