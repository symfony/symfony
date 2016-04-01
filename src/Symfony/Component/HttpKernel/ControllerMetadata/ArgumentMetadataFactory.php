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
            $arguments[] = new ArgumentMetadata($param->getName(), $this->getType($param), $this->isVariadic($param), $this->hasDefaultValue($param), $this->getDefaultValue($param));
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
        return PHP_VERSION_ID >= 50600 && $parameter->isVariadic();
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
        if (PHP_VERSION_ID >= 70000) {
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
            return str_replace(['Class ', ' does not exist'], '', $e->getMessage());
        }

        return $refClass ? $refClass->getName() : null;
    }
}
