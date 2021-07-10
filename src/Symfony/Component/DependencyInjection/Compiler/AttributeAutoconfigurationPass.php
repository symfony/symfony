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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class AttributeAutoconfigurationPass extends AbstractRecursivePass
{
    private $classAttributeConfigurators = [];
    private $methodAttributeConfigurators = [];
    private $propertyAttributeConfigurators = [];
    private $parameterAttributeConfigurators = [];

    public function process(ContainerBuilder $container): void
    {
        if (80000 > \PHP_VERSION_ID || !$container->getAutoconfiguredAttributes()) {
            return;
        }

        foreach ($container->getAutoconfiguredAttributes() as $attributeName => $callable) {
            $callableReflector = new \ReflectionFunction(\Closure::fromCallable($callable));
            if ($callableReflector->getNumberOfParameters() <= 2) {
                $this->classAttributeConfigurators[$attributeName] = $callable;
                continue;
            }

            $reflectorParameter = $callableReflector->getParameters()[2];
            $parameterType = $reflectorParameter->getType();
            $types = [];
            if ($parameterType instanceof \ReflectionUnionType) {
                foreach ($parameterType->getTypes() as $type) {
                    $types[] = $type->getName();
                }
            } elseif ($parameterType instanceof \ReflectionNamedType) {
                $types[] = $parameterType->getName();
            } else {
                throw new \LogicException('Callable passed to registerAttributeForAutoconfiguration() should have a type for parameter #3. Use one or more of the following types: \ReflectionClass|\ReflectionMethod|\ReflectionProperty|\ReflectionParameter.');
            }

            if (\in_array(\ReflectionClass::class, $types, true)) {
                $this->classAttributeConfigurators[$attributeName] = $callable;
            }

            if (\in_array(\ReflectionMethod::class, $types, true)) {
                $this->methodAttributeConfigurators[$attributeName] = $callable;
            }

            if (\in_array(\ReflectionProperty::class, $types, true)) {
                $this->propertyAttributeConfigurators[$attributeName] = $callable;
            }

            if (\in_array(\ReflectionParameter::class, $types, true)) {
                $this->parameterAttributeConfigurators[$attributeName] = $callable;
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

        if ($this->classAttributeConfigurators) {
            foreach ($classReflector->getAttributes() as $attribute) {
                if ($configurator = $this->classAttributeConfigurators[$attribute->getName()] ?? null) {
                    $configurator($conditionals, $attribute->newInstance(), $classReflector);
                }
            }
        }

        if ($this->methodAttributeConfigurators || $this->parameterAttributeConfigurators) {
            $constructorReflector = $this->getConstructor($value, false);
            if ($constructorReflector) {
                if ($this->parameterAttributeConfigurators) {
                    foreach ($constructorReflector->getParameters() as $parameterReflector) {
                        foreach ($parameterReflector->getAttributes() as $attribute) {
                            if ($configurator = $this->parameterAttributeConfigurators[$attribute->getName()] ?? null) {
                                $configurator($conditionals, $attribute->newInstance(), $parameterReflector);
                            }
                        }
                    }
                }
            }

            foreach ($classReflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $methodReflector) {
                if ($methodReflector->isStatic() || $methodReflector->isConstructor() || $methodReflector->isDestructor()) {
                    continue;
                }

                if ($this->methodAttributeConfigurators) {
                    foreach ($methodReflector->getAttributes() as $attribute) {
                        if ($configurator = $this->methodAttributeConfigurators[$attribute->getName()] ?? null) {
                            $configurator($conditionals, $attribute->newInstance(), $methodReflector);
                        }
                    }
                }

                if ($this->parameterAttributeConfigurators) {
                    foreach ($methodReflector->getParameters() as $parameterReflector) {
                        foreach ($parameterReflector->getAttributes() as $attribute) {
                            if ($configurator = $this->parameterAttributeConfigurators[$attribute->getName()] ?? null) {
                                $configurator($conditionals, $attribute->newInstance(), $parameterReflector);
                            }
                        }
                    }
                }
            }
        }

        if ($this->propertyAttributeConfigurators) {
            foreach ($classReflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $propertyReflector) {
                if ($propertyReflector->isStatic()) {
                    continue;
                }

                foreach ($propertyReflector->getAttributes() as $attribute) {
                    if ($configurator = $this->propertyAttributeConfigurators[$attribute->getName()] ?? null) {
                        $configurator($conditionals, $attribute->newInstance(), $propertyReflector);
                    }
                }
            }
        }

        if (!isset($instanceof[$classReflector->getName()]) && new ChildDefinition('') != $conditionals) {
            $instanceof[$classReflector->getName()] = $conditionals;
            $value->setInstanceofConditionals($instanceof);
        }

        return parent::processValue($value, $isRoot);
    }
}
