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
use Symfony\Component\Validator\Exception\ValidatorException;

abstract class MemberMetadata extends ElementMetadata
{
    public $class;
    public $name;
    public $property;
    public $cascaded = false;
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
        if ($constraint instanceof Valid) {
            $this->cascaded = true;
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
     * @return boolean
     */
    public function isPublic()
    {
        return $this->getReflectionMember()->isPublic();
    }

    /**
     * Returns whether this member is protected
     *
     * @return boolean
     */
    public function isProtected()
    {
        return $this->getReflectionMember()->isProtected();
    }

    /**
     * Returns whether this member is private
     *
     * @return boolean
     */
    public function isPrivate()
    {
        return $this->getReflectionMember()->isPrivate();
    }

    /**
     * Returns whether objects stored in this member should be validated
     *
     * @return boolean
     */
    public function isCascaded()
    {
        return $this->cascaded;
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