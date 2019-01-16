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
        $arguments = [];

        if (\is_array($controller)) {
            $reflection = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (\is_object($controller) && !$controller instanceof \Closure) {
            $reflection = (new \ReflectionObject($controller))->getMethod('__invoke');
        } else {
            $reflection = new \ReflectionFunction($controller);
        }

        foreach ($reflection->getParameters() as $param) {
            $arguments[] = new ArgumentMetadata($param->getName(), $this->getType($param, $reflection), $param->isVariadic(), $param->isDefaultValueAvailable(), $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null, $param->allowsNull());
        }

        return $arguments;
    }

    /**
     * Returns an associated type to the given parameter if available.
     *
     * @param \ReflectionParameter $parameter
     *
     * @return string|null
     */
    private function getType(\ReflectionParameter $parameter, \ReflectionFunctionAbstract $function)
    {
        if (!$type = $parameter->getType()) {
            return;
        }
        $name = $type->getName();
        $lcName = strtolower($name);

        if ('self' !== $lcName && 'parent' !== $lcName) {
            return $name;
        }
        if (!$function instanceof \ReflectionMethod) {
            return;
        }
        if ('self' === $lcName) {
            return $function->getDeclaringClass()->name;
        }
        if ($parent = $function->getDeclaringClass()->getParentClass()) {
            return $parent->name;
        }
    }
}
