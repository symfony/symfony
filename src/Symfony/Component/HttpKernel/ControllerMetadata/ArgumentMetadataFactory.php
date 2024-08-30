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

use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Builds {@see ArgumentMetadata} objects based on the given Controller.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class ArgumentMetadataFactory implements ArgumentMetadataFactoryInterface
{
    public function __construct(private readonly ?TypeResolverInterface $typeResolver = null)
    {
    }

    public function createArgumentMetadata(string|object|array $controller, ?\ReflectionFunctionAbstract $reflector = null): array
    {
        $arguments = [];
        $reflector ??= new \ReflectionFunction($controller(...));
        $controllerName = $this->getPrettyName($reflector);

        foreach ($reflector->getParameters() as $param) {
            $attributes = [];
            foreach ($param->getAttributes() as $reflectionAttribute) {
                if (class_exists($reflectionAttribute->getName())) {
                    $attributes[] = $reflectionAttribute->newInstance();
                }
            }

            $arguments[] = new ArgumentMetadata($param->getName(), $this->getType($param), $param->isVariadic(), $param->isDefaultValueAvailable(), $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null, $param->allowsNull(), $attributes, $controllerName);
        }

        return $arguments;
    }

    /**
     * Returns an associated type to the given parameter if available.
     */
    private function getType(\ReflectionParameter $parameter): ?string
    {
        if (!$type = $parameter->getType()) {
            return null;
        }

        if ($this->typeResolver) {
            $type = $this->typeResolver->resolve($parameter);

            if ($type instanceof CollectionType) {
                return (string) $type->getCollectionValueType().'[]';
            }

            return $type->getBaseType()->getTypeIdentifier()->value;
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
