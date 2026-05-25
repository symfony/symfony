<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Looks for definitions with autowiring enabled and registers their corresponding "#[Required]" properties.
 *
 * @author Sebastien Morel (Plopix) <morel.seb@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AutowireRequiredPropertiesPass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        $value = parent::processValue($value, $isRoot);

        if (!$value instanceof Definition || !$value->isAutowired() || $value->isAbstract() || !$value->getClass()) {
            return $value;
        }
        if (!$reflectionClass = $this->container->getReflectionClass($value->getClass(), false)) {
            return $value;
        }

        $properties = $value->getProperties();
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if (!($type = $reflectionProperty->getType()) instanceof \ReflectionNamedType) {
                continue;
            }
            if (!$reflectionProperty->getAttributes(Required::class)) {
                continue;
            }
            if (\array_key_exists($name = $reflectionProperty->getName(), $properties)) {
                continue;
            }
            if (
                $reflectionProperty->isPrivateSet()
                || $reflectionProperty->isProtectedSet()
                || !$reflectionProperty->isPublic()
            ) {
                throw new InvalidArgumentException(\sprintf('Cannot autowire non-public(set) property "%s::$%s" with #[%s].', $reflectionClass->getName(), $reflectionProperty->getName(), Required::class));
            }

            $type = $type->getName();
            $value->setProperty($name, new TypedReference($type, $type, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $name, array_map(static fn ($a) => $a->newInstance(), array_merge(
                $reflectionProperty->getAttributes(Autowire::class, \ReflectionAttribute::IS_INSTANCEOF),
                $reflectionProperty->getAttributes(AutowireDecorated::class),
                $reflectionProperty->getAttributes(Target::class),
            ))));
        }

        return $value;
    }
}
