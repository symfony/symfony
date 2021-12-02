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
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

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
        if (!$container->getAutoconfiguredAttributes()) {
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
                throw new LogicException(sprintf('Argument "$%s" of attribute autoconfigurator should have a type, use one or more of "\ReflectionClass|\ReflectionMethod|\ReflectionProperty|\ReflectionParameter|\Reflector" in "%s" on line "%d".', $reflectorParameter->getName(), $callableReflector->getFileName(), $callableReflector->getStartLine()));
            }

            try {
                $attributeReflector = new \ReflectionClass($attributeName);
            } catch (\ReflectionException $e) {
                continue;
            }

            $targets = $attributeReflector->getAttributes(\Attribute::class)[0] ?? 0;
            $targets = $targets ? $targets->getArguments()[0] ?? -1 : 0;

            foreach (['class', 'method', 'property', 'parameter'] as $symbol) {
                if (['Reflector'] !== $types) {
                    if (!\in_array('Reflection'.ucfirst($symbol), $types, true)) {
                        continue;
                    }
                    if (!($targets & \constant('Attribute::TARGET_'.strtoupper($symbol)))) {
                        throw new LogicException(sprintf('Invalid type "Reflection%s" on argument "$%s": attribute "%s" cannot target a '.$symbol.' in "%s" on line "%d".', ucfirst($symbol), $reflectorParameter->getName(), $attributeName, $callableReflector->getFileName(), $callableReflector->getStartLine()));
                    }
                }
                $this->{$symbol.'AttributeConfigurators'}[$attributeName] = $callable;
            }
        }

        parent::process($container);
    }

    protected function processValue(mixed $value, bool $isRoot = false): mixed
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

        if ($this->parameterAttributeConfigurators) {
            try {
                $constructorReflector = $this->getConstructor($value, false);
            } catch (RuntimeException $e) {
                $constructorReflector = null;
            }

            if ($constructorReflector) {
                foreach ($constructorReflector->getParameters() as $parameterReflector) {
                    foreach ($parameterReflector->getAttributes() as $attribute) {
                        if ($configurator = $this->parameterAttributeConfigurators[$attribute->getName()] ?? null) {
                            $configurator($conditionals, $attribute->newInstance(), $parameterReflector);
                        }
                    }
                }
            }
        }

        if ($this->methodAttributeConfigurators || $this->parameterAttributeConfigurators) {
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
