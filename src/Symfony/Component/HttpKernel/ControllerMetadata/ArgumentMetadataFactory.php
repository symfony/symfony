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
     * Default values are read again and attributes are cloned on each call, so that callers never share objects.
     *
     * Static since it only holds what the code declares, which is the same for all instances.
     *
     * @var array<string, list<array{string, ?string, bool, ?\ReflectionParameter, bool, list<object>, string}>>
     */
    private static array $parametersCache = [];

    public function createArgumentMetadata(string|object|array $controller, ?\ReflectionFunctionAbstract $reflector = null): array
    {
        $reflector ??= new \ReflectionFunction($controller(...));

        // anonymous closures and Closure::__invoke() have no name that tells them apart, so they are not cached
        $key = match (true) {
            $reflector instanceof \ReflectionMethod => \Closure::class === $reflector->class ? null : $reflector->class.'::'.$reflector->name,
            $reflector instanceof \ReflectionFunction && !$reflector->isAnonymous() => $reflector->getClosureCalledClass()?->name.'::'.$reflector->name.'@'.$reflector->getClosureScopeClass()?->name,
            default => null,
        };
        $parameters = null === $key ? $this->getParameters($reflector) : self::$parametersCache[$key] ??= $this->getParameters($reflector);
        $arguments = [];

        foreach ($parameters as [$name, $type, $isVariadic, $param, $isNullable, $attributes, $controllerName]) {
            foreach ($attributes as $i => $attribute) {
                $attributes[$i] = clone $attribute;
            }

            $arguments[] = new ArgumentMetadata($name, $type, $isVariadic, null !== $param, $param?->getDefaultValue(), $isNullable, $attributes, $controllerName);
        }

        return $arguments;
    }

    /**
     * @return list<array{string, ?string, bool, ?\ReflectionParameter, bool, list<object>, string}>
     */
    private function getParameters(\ReflectionFunctionAbstract $reflector): array
    {
        $parameters = [];
        $controllerName = $this->getPrettyName($reflector);

        foreach ($reflector->getParameters() as $param) {
            $attributes = [];
            foreach ($param->getAttributes() as $reflectionAttribute) {
                if (class_exists($reflectionAttribute->getName())) {
                    $attributes[] = $reflectionAttribute->newInstance();
                }
            }

            $parameters[] = [$param->getName(), $this->getType($param), $param->isVariadic(), $param->isDefaultValueAvailable() ? $param : null, $param->allowsNull(), $attributes, $controllerName];
        }

        return $parameters;
    }

    /**
     * Returns an associated type to the given parameter if available.
     */
    private function getType(\ReflectionParameter $parameter): ?string
    {
        if (!$type = $parameter->getType()) {
            return null;
        }
        $name = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;

        return match (strtolower($name)) {
            'self' => $parameter->getDeclaringClass()?->name,
            'parent' => get_parent_class($parameter->getDeclaringClass()?->name ?? '') ?: null,
            default => $name,
        };
    }

    private function getPrettyName(\ReflectionFunctionAbstract $r): string
    {
        $name = $r->name;

        if ($r instanceof \ReflectionMethod) {
            return $r->class.'::'.$name;
        }

        if ($r->isAnonymous() || !$class = $r->getClosureCalledClass()) {
            return $name;
        }

        return $class->name.'::'.$name;
    }
}
