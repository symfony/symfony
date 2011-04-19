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
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

abstract class MemberMetadata extends ElementMetadata
{
    public $class;
    public $name;
    public $property;
    public $cascaded = false;
    public $collectionCascaded = false;
    private $reflMember;

    /**
     * Constructor.
     *
     * @param string $class    The name of the class this member is defined on
     * @param string $name     The name of the member
     * @param string $property The property the member belongs to
     */
    public function __construct($class, $name, $property)
    {
        $this->class = $class;
        $this->name = $name;
        $this->property = $property;
    }

    /**
     * {@inheritDoc}
     */
    public function addConstraint(Constraint $constraint)
    {
        if (!in_array(Constraint::PROPERTY_CONSTRAINT, (array) $constraint->getTargets())) {
            throw new ConstraintDefinitionException(sprintf(
                'The constraint %s cannot be put on properties or getters',
                get_class($constraint)
            ));
        }

        if ($constraint instanceof Valid) {
            $this->cascaded = true;
            $this->collectionCascaded = $constraint->traverse;
        } else {
            parent::addConstraint($constraint);
        }

        return $this;
    }

    /**
     * Returns the names of the properties that should be serialized
     *
     * @return array
     */
    public function __sleep()
    {
        return array_merge(parent::__sleep(), array(
            'class',
            'name',
            'property',
            'cascaded', // TESTME
        ));
    }

    /**
     * Returns the name of the member
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the class this member is defined on
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->class;
    }

    /**
     * Returns the name of the property this member belongs to
     *
     * @return string The property name
     */
    public function getPropertyName()
    {
        return $this->property;
    }

    /**
     * Returns whether this member is public
     *
     * @return Boolean
     */
    public function isPublic()
    {
        return $this->getReflectionMember()->isPublic();
    }

    /**
     * Returns whether this member is protected
     *
     * @return Boolean
     */
    public function isProtected()
    {
        return $this->getReflectionMember()->isProtected();
    }

    /**
     * Returns whether this member is private
     *
     * @return Boolean
     */
    public function isPrivate()
    {
        return $this->getReflectionMember()->isPrivate();
    }

    /**
     * Returns whether objects stored in this member should be validated
     *
     * @return Boolean
     */
    public function isCascaded()
    {
        return $this->cascaded;
    }

    /**
     * Returns whether arrays or traversable objects stored in this member
     * should be traversed and validated in each entry
     *
     * @return Boolean
     */
    public function isCollectionCascaded()
    {
        return $this->collectionCascaded;
    }

    /**
     * Returns the value of this property in the given object
     *
     * @param object $object The object
     *
     * @return mixed The property value
     */
    abstract public function getValue($object);

    /**
     * Returns the Reflection instance of the member
     *
     * @return object
     */
    public function getReflectionMember()
    {
        if (!$this->reflMember) {
            $this->reflMember = $this->newReflectionMember();
        }

        return $this->reflMember;
    }

    /**
     * Creates a new Reflection instance for the member
     *
     * @return object
     */
    abstract protected function newReflectionMember();
}