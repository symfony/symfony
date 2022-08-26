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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Looks for definitions with autowiring enabled and registers their corresponding "@required" properties.
 *
 * @author Sebastien Morel (Plopix) <morel.seb@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AutowireRequiredPropertiesPass extends AbstractRecursivePass
{
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
            if (!$reflectionProperty->getAttributes(Required::class)
                && ((false === $doc = $reflectionProperty->getDocComment()) || false === stripos($doc, '@required') || !preg_match('#(?:^/\*\*|\n\s*+\*)\s*+@required(?:\s|\*/$)#i', $doc))
            ) {
                continue;
            }
            if (\array_key_exists($name = $reflectionProperty->getName(), $properties)) {
                continue;
            }

            $type = $type->getName();
            $value->setProperty($name, new TypedReference($type, $type, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $name));
        }

        return $value;
    }
}
