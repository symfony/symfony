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

/**
 * @since v2.0.0
 */
class GetterMetadata extends MemberMetadata
{
    /**
     * Constructor.
     *
     * @param string $class    The class the getter is defined on
     * @param string $property The property which the getter returns
     *
     * @throws ValidatorException
     *
     * @since v2.0.0
     */
    public function __construct($class, $property)
    {
        $getMethod = 'get'.ucfirst($property);
        $isMethod = 'is'.ucfirst($property);

        if (method_exists($class, $getMethod)) {
            $method = $getMethod;
        } elseif (method_exists($class, $isMethod)) {
            $method = $isMethod;
        } else {
            throw new ValidatorException(sprintf('Neither method %s nor %s exists in class %s', $getMethod, $isMethod, $class));
        }

        parent::__construct($class, $method, $property);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.2.0
     */
    public function getPropertyValue($object)
    {
        return $this->newReflectionMember($object)->invoke($object);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    protected function newReflectionMember($objectOrClassName)
    {
        return new \ReflectionMethod($objectOrClassName, $this->getName());
    }
}
