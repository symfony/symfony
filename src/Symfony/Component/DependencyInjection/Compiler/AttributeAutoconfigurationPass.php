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

use LogicException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class AttributeAutoconfigurationPass extends AbstractRecursivePass
{
    private $classAttributes = [];
    private $methodAttributes = [];
    private $propertyAttributes = [];

    public function process(ContainerBuilder $container): void
    {
        if (80000 > \PHP_VERSION_ID || !$container->getAutoconfiguredAttributes()) {
            return;
        }

        foreach ($container->getAutoconfiguredAttributes() as $attributeName => $callable) {
            $callableReflector = new \ReflectionFunction(\Closure::fromCallable($callable));
            if ($callableReflector->getNumberOfParameters() <= 2) {
                $this->classAttributes[$attributeName] = $callable;
                continue;
            }

            $reflectorParameter = $callableReflector->getParameters()[2];
            $parameterType = $reflectorParameter->getType();
            $types = [];
            if ($parameterType instanceof \ReflectionUnionType) {
                foreach ($parameterType->getTypes() as $type) {
                    $types[] = $type->getName();
                }
            } else {
                if ($parameterType === null) {
                    throw new LogicException('Callable parameter 3 should have a type.');
                }
                $types[] = $parameterType->getName();
            }

            if (in_array(\ReflectionClass::class, $types, true)) {
                $this->classAttributes[$attributeName] = $callable;
            }

            if (in_array(\ReflectionMethod::class, $types, true)) {
                $this->methodAttributes[$attributeName] = $callable;
            }

            if (in_array(\ReflectionProperty::class, $types, true)) {
                $this->propertyAttributes[$attributeName] = $callable;
            }
        }

        parent::process($container);
    }

    protected function processValue($value, bool $isRoot = false)
    {
        if (!$value instanceof Definition
            || !$value->isAutoconfigured()
            || $value->isAbstract()
            || $value->hasTag('container.ignore_attributes')
            || !($classReflector = $this->container->getReflectionClass($value->getClass(), false))
        ) {
            return parent::processValue($value, $isRoot);
        }

        $instanceof = $value->getInstanceofConditionals();
        $conditionals = $instanceof[$classReflector->getName()] ?? new ChildDefinition('');

        if (count($this->classAttributes) > 0) {
            foreach ($classReflector->getAttributes() as $attribute) {
                if ($configurator = $this->classAttributes[$attribute->getName()] ?? null) {
                    $configurator($conditionals, $attribute->newInstance(), $classReflector);
                }
            }
        }

        if (count($this->methodAttributes) > 0) {
            foreach ($classReflector->getMethods(\ReflectionMethod::IS_PUBLIC & ~\ReflectionMethod::IS_STATIC) as $methodReflector) {
                foreach ($methodReflector->getAttributes() as $attribute) {
                    if ($configurator = $this->methodAttributes[$attribute->getName()] ?? null) {
                        $configurator($conditionals, $attribute->newInstance(), $methodReflector);
                    }
                }
            }
        }

        if (count($this->propertyAttributes) > 0) {
            foreach ($classReflector->getProperties(~\ReflectionProperty::IS_STATIC) as $propertyReflector) {
                foreach ($propertyReflector->getAttributes() as $attribute) {
                    if ($configurator = $this->propertyAttributes[$attribute->getName()] ?? null) {
                        $configurator($conditionals, $attribute->newInstance(), $propertyReflector);
                    }
                }
            }
        }

        // @todo parameters... on what? Constructor or all methods?

        if (!isset($instanceof[$classReflector->getName()]) && new ChildDefinition('') != $conditionals) {
            $instanceof[$classReflector->getName()] = $conditionals;
            $value->setInstanceofConditionals($instanceof);
        }

        return parent::processValue($value, $isRoot);
    }
}
