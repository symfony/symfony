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
    protected bool $skipScalars = true;

    private array $classAttributeConfigurators = [];
    private array $methodAttributeConfigurators = [];
    private array $propertyAttributeConfigurators = [];
    private array $parameterAttributeConfigurators = [];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->getAttributeAutoconfigurators()) {
            return;
        }

        foreach ($container->getAttributeAutoconfigurators() as $attributeName => $callables) {
            foreach ($callables as $callable) {
                $callableReflector = new \ReflectionFunction($callable(...));
                if ($callableReflector->getNumberOfParameters() <= 2) {
                    $this->classAttributeConfigurators[$attributeName][] = $callable;
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
                    throw new LogicException(\sprintf('Argument "$%s" of attribute autoconfigurator should have a type, use one or more of "\ReflectionClass|\ReflectionMethod|\ReflectionProperty|\ReflectionParameter|\Reflector" in "%s" on line "%d".', $reflectorParameter->getName(), $callableReflector->getFileName(), $callableReflector->getStartLine()));
                }

                try {
                    $attributeReflector = new \ReflectionClass($attributeName);
                } catch (\ReflectionException) {
                    continue;
                }

                $targets = $attributeReflector->getAttributes(\Attribute::class)[0] ?? 0;
                $targets = $targets ? $targets->getArguments()[0] ?? -1 : 0;

                foreach (['class', 'method', 'property', 'parameter'] as $symbol) {
                    if (['Reflector'] !== $types) {
                        if (!\in_array('Reflection' . ucfirst($symbol), $types, true)) {
                            continue;
                        }
                        if (!($targets & \constant('Attribute::TARGET_' . strtoupper($symbol)))) {
                            throw new LogicException(\sprintf('Invalid type "Reflection%s" on argument "$%s": attribute "%s" cannot target a ' . $symbol . ' in "%s" on line "%d".', ucfirst($symbol), $reflectorParameter->getName(), $attributeName, $callableReflector->getFileName(), $callableReflector->getStartLine()));
                        }
                    }
                    $this->{$symbol . 'AttributeConfigurators'}[$attributeName][] = $callable;
                }
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

        $this->callConfigurators($this->classAttributeConfigurators, $conditionals, $classReflector);

        if ($this->parameterAttributeConfigurators) {
            try {
                $constructorReflector = $this->getConstructor($value, false);
            } catch (RuntimeException) {
                $constructorReflector = null;
            }

            if ($constructorReflector) {
                foreach ($constructorReflector->getParameters() as $parameterReflector) {
                    $this->callConfigurators($this->parameterAttributeConfigurators, $conditionals, $parameterReflector);
                }
            }
        }

        if ($this->methodAttributeConfigurators || $this->parameterAttributeConfigurators) {
            foreach ($classReflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $methodReflector) {
                if ($methodReflector->isConstructor() || $methodReflector->isDestructor()) {
                    continue;
                }

                $this->callConfigurators($this->methodAttributeConfigurators, $conditionals, $methodReflector);

                foreach ($methodReflector->getParameters() as $parameterReflector) {
                    $this->callConfigurators($this->parameterAttributeConfigurators, $conditionals, $parameterReflector);
                }
            }
        }

        if ($this->propertyAttributeConfigurators) {
            foreach ($classReflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $propertyReflector) {
                if ($propertyReflector->isStatic()) {
                    continue;
                }

                $this->callConfigurators($this->propertyAttributeConfigurators, $conditionals, $propertyReflector);
            }
        }

        if (!isset($instanceof[$classReflector->getName()]) && new ChildDefinition('') != $conditionals) {
            $instanceof[$classReflector->getName()] = $conditionals;
            $value->setInstanceofConditionals($instanceof);
        }

        return parent::processValue($value, $isRoot);
    }

    /**
     * Call all the configurators for the given attribute.
     *
     * @param array<class-string, callable[]> $configurators
     */
    private function callConfigurators(array &$configurators, ChildDefinition $conditionals, \ReflectionClass|\ReflectionMethod|\ReflectionParameter|\ReflectionProperty $reflector): void
    {
        if (!$configurators) {
            return;
        }

        foreach ($reflector->getAttributes() as $attribute) {
            foreach ($this->findConfigurators($configurators, $attribute->getName()) as $configurator) {
                $configurator($conditionals, $attribute->newInstance(), $reflector);
            }
        }
    }

    /**
     * Find the first configurator for the given attribute name, looking up the class hierarchy.
     */
    private function findConfigurators(array &$configurators, string $attributeName): array
    {
        if (\array_key_exists($attributeName, $configurators)) {
            return $configurators[$attributeName];
        }

        if (class_exists($attributeName) && $parent = get_parent_class($attributeName)) {
            return $configurators[$attributeName] = $this->findConfigurators($configurators, $parent);
        }

        return $configurators[$attributeName] = [];
    }
}
