<?php

namespace Symfony\Components\Validator\Mapping;

use Symfony\Components\Validator\Exception\ValidatorException;

abstract class MemberMetadata extends ElementMetadata
{
    public $class;
    public $name;
    public $property;
    private $reflMember;

    /**
     * Constructor.
     *
     * @param string $class     The name of the class this member is defined on
     * @param string $name      The name of the member
     * @param string $property  The property the member belongs to
     */
    public function __construct($class, $name, $property)
    {
        $this->class = $class;
        $this->name = $name;
        $this->property = $property;
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
            'property'
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
     * @return string  The property name
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
     * Returns the value of this property in the given object
     *
     * @param  object $object  The object
     * @return mixed           The property value
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