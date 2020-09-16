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

use Symfony\Component\HttpKernel\Attribute\ArgumentInterface;
use Symfony\Component\HttpKernel\Exception\InvalidMetadataException;

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
    public function createArgumentMetadata($controller): array
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
            $attribute = null;
            if (\PHP_VERSION_ID >= 80000) {
                $reflectionAttributes = $param->getAttributes(ArgumentInterface::class, \ReflectionAttribute::IS_INSTANCEOF);

                if (\count($reflectionAttributes) > 1) {
                    $representative = $controller;

                    if (\is_array($representative)) {
                        $representative = sprintf('%s::%s()', \get_class($representative[0]), $representative[1]);
                    } elseif (\is_object($representative)) {
                        $representative = \get_class($representative);
                    }

                    throw new InvalidMetadataException(sprintf('Controller "%s" has more than one attribute for "$%s" argument.', $representative, $param->getName()));
                }

                if (isset($reflectionAttributes[0])) {
                    $attribute = $reflectionAttributes[0]->newInstance();
                }
            }

            $arguments[] = new ArgumentMetadata($param->getName(), $this->getType($param, $reflection), $param->isVariadic(), $param->isDefaultValueAvailable(), $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null, $param->allowsNull(), $attribute);
        }

        return $arguments;
    }

    /**
     * Returns an associated type to the given parameter if available.
     */
    private function getType(\ReflectionParameter $parameter, \ReflectionFunctionAbstract $function): ?string
    {
        if (!$type = $parameter->getType()) {
            return null;
        }
        $name = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;

        if ($function instanceof \ReflectionMethod) {
            $lcName = strtolower($name);
            switch ($lcName) {
                case 'self':
                    return $function->getDeclaringClass()->name;
                case 'parent':
                    return ($parent = $function->getDeclaringClass()->getParentClass()) ? $parent->name : null;
            }
        }

        return $name;
    }
}
