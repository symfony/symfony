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
        $arguments = [];

        if (\is_array($controller)) {
            $reflection = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (\is_object($controller) && !$controller instanceof \Closure) {
            $reflection = (new \ReflectionObject($controller))->getMethod('__invoke');
        } else {
            $reflection = new \ReflectionFunction($controller);
        }

        foreach ($reflection->getParameters() as $param) {
            $arguments[] = new ArgumentMetadata($param->getName(), $this->getType($param, $reflection), $this->isVariadic($param), $this->hasDefaultValue($param), $this->getDefaultValue($param), $param->allowsNull());
        }

        return $arguments;
    }

    /**
     * Returns whether an argument is variadic.
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
     * @return bool
     */
    private function hasDefaultValue(\ReflectionParameter $parameter)
    {
        return $parameter->isDefaultValueAvailable();
    }

    /**
     * Returns a default value if available.
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
     * @return string|null
     */
    private function getType(\ReflectionParameter $parameter, \ReflectionFunctionAbstract $function)
    {
        if ($this->supportsParameterType) {
            if (!$type = $parameter->getType()) {
                return null;
            }
            $name = $type instanceof \ReflectionNamedType ? $type->getName() : $type->__toString();
            if ('array' === $name && !$type->isBuiltin()) {
                // Special case for HHVM with variadics
                return null;
            }
        } elseif (preg_match('/^(?:[^ ]++ ){4}([a-zA-Z_\x7F-\xFF][^ ]++)/', $parameter, $name)) {
            $name = $name[1];
        } else {
            return null;
        }
        $lcName = strtolower($name);

        if ('self' !== $lcName && 'parent' !== $lcName) {
            return $name;
        }
        if (!$function instanceof \ReflectionMethod) {
            return null;
        }
        if ('self' === $lcName) {
            return $function->getDeclaringClass()->name;
        }
        if ($parent = $function->getDeclaringClass()->getParentClass()) {
            return $parent->name;
        }

        return null;
    }
}
