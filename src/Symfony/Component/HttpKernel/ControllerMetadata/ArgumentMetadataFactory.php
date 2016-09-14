<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\ControllerMetadata;

/**
 * Builds {@see ArgumentMetadata} objects based on the given Controller.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class ArgumentMetadataFactory implements ArgumentMetadataFactoryInterface
{
    /**
     * If the ...$arg functionality is available.
     *
     * Requires at least PHP 5.6.0 or HHVM 3.9.1
     *
     * @var bool
     */
    private $supportsVariadic;

    /**
     * If the reflection supports the getType() method to resolve types.
     *
     * Requires at least PHP 7.0.0 or HHVM 3.11.0
     *
     * @var bool
     */
    private $supportsParameterType;

    public function __construct()
    {
        $this->supportsVariadic = method_exists('ReflectionParameter', 'isVariadic');
        $this->supportsParameterType = method_exists('ReflectionParameter', 'getType');
    }

    /**
     * {@inheritdoc}
     */
    public function createArgumentMetadata($controller)
    {
        $arguments = array();

        if (is_array($controller)) {
            $reflection = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (is_object($controller) && !$controller instanceof \Closure) {
            $reflection = (new \ReflectionObject($controller))->getMethod('__invoke');
        } else {
            $reflection = new \ReflectionFunction($controller);
        }

        foreach ($reflection->getParameters() as $param) {
            $arguments[] = new ArgumentMetadata($param->getName(), $this->getType($param), $this->isVariadic($param), $this->hasDefaultValue($param), $this->getDefaultValue($param), $this->isNullable($param));
        }

        return $arguments;
    }

    /**
     * Returns whether an argument is variadic.
     *
     * @param \ReflectionParameter $parameter
     *
     * @return bool
     */
    private function isVariadic(\ReflectionParameter $parameter)
    {
        return $this->supportsVariadic && $parameter->isVariadic();
    }

    /**
     * Determines whether an argument has a default value.
     *
     * @param \ReflectionParameter $parameter
     *
     * @return bool
     */
    private function hasDefaultValue(\ReflectionParameter $parameter)
    {
        return $parameter->isDefaultValueAvailable();
    }

    /**
     * Returns if the argument is allowed to be null but is still mandatory.
     *
     * @param \ReflectionParameter $parameter
     *
     * @return bool
     */
    private function isNullable(\ReflectionParameter $parameter)
    {
        if ($this->supportsParameterType) {
            return null !== ($type = $parameter->getType()) && $type->allowsNull();
        }

        // fallback for supported php 5.x versions
        return $this->hasDefaultValue($parameter) && null === $this->getDefaultValue($parameter);
    }

    /**
     * Returns a default value if available.
     *
     * @param \ReflectionParameter $parameter
     *
     * @return mixed|null
     */
    private function getDefaultValue(\ReflectionParameter $parameter)
    {
        return $this->hasDefaultValue($parameter) ? $parameter->getDefaultValue() : null;
    }

    /**
     * Returns an associated type to the given parameter if available.
     *
     * @param \ReflectionParameter $parameter
     *
     * @return null|string
     */
    private function getType(\ReflectionParameter $parameter)
    {
        if ($this->supportsParameterType) {
            return $parameter->hasType() ? (string) $parameter->getType() : null;
        }

        if ($parameter->isArray()) {
            return 'array';
        }

        if ($parameter->isCallable()) {
            return 'callable';
        }

        try {
            $refClass = $parameter->getClass();
        } catch (\ReflectionException $e) {
            // mandatory; extract it from the exception message
            return str_replace(array('Class ', ' does not exist'), '', $e->getMessage());
        }

        return $refClass ? $refClass->getName() : null;
    }
}
