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
    public function createArgumentMetadata(string|object|array $controller, \ReflectionClass $class = null, \ReflectionFunctionAbstract $reflection = null): array
    {
        $arguments = [];

        if (null === $reflection) {
            $reflection = new \ReflectionFunction($controller(...));
            $class = str_contains($reflection->name, '{closure}') ? null : $reflection->getClosureScopeClass();
        }
        $class = $class?->name;

        foreach ($reflection->getParameters() as $param) {
            $attributes = [];
            foreach ($param->getAttributes() as $reflectionAttribute) {
                if (class_exists($reflectionAttribute->getName())) {
                    $attributes[] = $reflectionAttribute->newInstance();
                }
            }

            $arguments[] = new ArgumentMetadata($param->getName(), $this->getType($param, $class), $param->isVariadic(), $param->isDefaultValueAvailable(), $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null, $param->allowsNull(), $attributes);
        }

        return $arguments;
    }

    /**
     * Returns an associated type to the given parameter if available.
     */
    private function getType(\ReflectionParameter $parameter, ?string $class): ?string
    {
        if (!$type = $parameter->getType()) {
            return null;
        }
        $name = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;

        if (null !== $class) {
            switch (strtolower($name)) {
                case 'self':
                    return $class;
                case 'parent':
                    return get_parent_class($class) ?: null;
            }
        }

        return $name;
    }
}
