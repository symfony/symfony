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

use Symfony\Component\Validator\Exception\ValidatorException;

class GetterMetadata extends MemberMetadata
{
    /**
     * Constructor.
     *
     * @param string $class    The class the getter is defined on
     * @param string $property The property which the getter returns
     *
     * @throws ValidatorException
     */
    public function __construct($class, $property)
    {
        $getMethod = 'get'.ucfirst($property);
        $isMethod = 'is'.ucfirst($property);
        $hasMethod = 'has'.ucfirst($property);

        if (method_exists($class, $getMethod)) {
            $method = $getMethod;
        } elseif (method_exists($class, $isMethod)) {
            $method = $isMethod;
        } elseif (method_exists($class, $hasMethod)) {
            $method = $hasMethod;
        } else {
            throw new ValidatorException(sprintf('Neither of these methods exist in class %s: %s, %s, %s', $class, $getMethod, $isMethod, $hasMethod));
        }

        parent::__construct($class, $method, $property);
    }

    /**
     * {@inheritDoc}
     */
    public function getPropertyValue($object)
    {
        return $this->newReflectionMember($object)->invoke($object);
    }

    /**
     * {@inheritDoc}
     */
    protected function newReflectionMember($objectOrClassName)
    {
        return new \ReflectionMethod($objectOrClassName, $this->getName());
    }
}
