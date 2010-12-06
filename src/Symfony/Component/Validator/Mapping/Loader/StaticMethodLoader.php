<?php

namespace Symfony\Component\Validator\Mapping\Loader;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;

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

            if ($reflMethod->getDeclaringClass()->getName() != $reflClass->getName()) {
                return false;
            }

            $reflMethod->invoke(null, $metadata);

            return true;
        }

        return false;
    }
}
