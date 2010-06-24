<?php

namespace Symfony\Components\Validator\Mapping;

use Symfony\Components\Validator\Exception\ValidatorException;

class GetterMetadata extends MemberMetadata
{
    /**
     * Constructor.
     *
     * @param string $class     The class the getter is defined on
     * @param string $property  The property which the getter returns
     */
    public function __construct($class, $property)
    {
        $getMethod = 'get'.ucfirst($property);
        $isMethod = 'is'.ucfirst($property);

        if (method_exists($class, $getMethod)) {
            $method = $getMethod;
        } else if (method_exists($class, $isMethod)) {
            $method = $isMethod;
        } else {
            throw new ValidatorException(sprintf('Neither method %s nor %s exists in class %s', $getMethod, $isMethod, $class));
        }

        parent::__construct($class, $method, $property);
    }

    /**
     * {@inheritDoc}
     */
    public function getValue($object)
    {
        return $this->getReflectionMember()->invoke($object);
    }

    /**
     * {@inheritDoc}
     */
    protected function newReflectionMember()
    {
        return new \ReflectionMethod($this->getClassName(), $this->getName());
    }
}