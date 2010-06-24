<?php

namespace Symfony\Components\Validator\Mapping\Loader;

use Symfony\Components\Validator\Exception\MappingException;
use Symfony\Components\Validator\Mapping\ClassMetadata;

class StaticMethodLoader implements LoaderInterface
{
    protected $methodName;

    public function __construct($methodName)
    {
        $this->methodName = $methodName;
    }

    /**
     * {@inheritDoc}
     */
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $reflClass = $metadata->getReflectionClass();

        if ($reflClass->hasMethod($this->methodName)) {
            $reflMethod = $reflClass->getMethod($this->methodName);

            if (!$reflMethod->isStatic()) {
                throw new MappingException(sprintf('The method %s::%s should be static', $reflClass->getName(), $this->methodName));
            }

            $reflMethod->invoke(null, $metadata);

            return true;
        }

        return false;
    }
}