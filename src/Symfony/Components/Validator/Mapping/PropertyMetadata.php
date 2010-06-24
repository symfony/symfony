<?php

namespace Symfony\Components\Validator\Mapping;

use Symfony\Components\Validator\Exception\ValidatorException;

class PropertyMetadata extends MemberMetadata
{
    /**
     * Constructor.
     *
     * @param string $class  The class this property is defined on
     * @param string $name   The name of this property
     */
    public function __construct($class, $name)
    {
        if (!property_exists($class, $name)) {
            throw new ValidatorException(sprintf('Property %s does not exists in class %s', $name, $class));
        }

        parent::__construct($class, $name, $name);
    }

    /**
     * {@inheritDoc}
     */
    public function getValue($object)
    {
        return $this->getReflectionMember()->getValue($object);
    }

    /**
     * {@inheritDoc}
     */
    protected function newReflectionMember()
    {
        $member = new \ReflectionProperty($this->getClassName(), $this->getName());
        $member->setAccessible(true);

        return $member;
    }
}