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

use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Attribute\BindTaggedIterator;
use Symfony\Component\DependencyInjection\Attribute\BindTaggedLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class AttributeAutoconfigurationPass extends AbstractRecursivePass
{
    /** @var array<string, callable>|null */
    private $argumentConfigurators;

    public function process(ContainerBuilder $container): void
    {
        if (80000 > \PHP_VERSION_ID) {
            return;
        }

        $this->argumentConfigurators = [
            BindTaggedIterator::class => static function (BindTaggedIterator $attribute) {
                return new TaggedIteratorArgument($attribute->tag, $attribute->indexAttribute);
            },
            BindTaggedLocator::class => static function (BindTaggedLocator $attribute) {
                return new ServiceLocatorArgument(new TaggedIteratorArgument($attribute->tag, $attribute->indexAttribute));
            },
        ];

        parent::process($container);

        $this->argumentConfigurators = null;
    }

    protected function processValue($value, bool $isRoot = false)
    {
        if ($value instanceof Definition
            && $value->isAutoconfigured()
            && !$value->isAbstract()
            && !$value->hasTag('container.ignore_attributes')
        ) {
            $value = $this->processDefinition($value);
        }

        return parent::processValue($value, $isRoot);
    }

    private function processDefinition(Definition $definition): Definition
    {
        if (!$reflector = $this->container->getReflectionClass($definition->getClass(), false)) {
            return $definition;
        }

        $autoconfiguredAttributes = $this->container->getAutoconfiguredAttributes();

        $instanceof = $definition->getInstanceofConditionals();
        $conditionals = $instanceof[$reflector->getName()] ?? new ChildDefinition('');
        foreach ($reflector->getAttributes() as $attribute) {
            if ($configurator = $autoconfiguredAttributes[$attribute->getName()] ?? null) {
                $configurator($conditionals, $attribute->newInstance(), $reflector);
            }
        }

        if ($constructor = $this->getConstructor($definition, false)) {
            $definition = $this->bindArguments($definition, $constructor);
        }

        $instanceof[$reflector->getName()] = $conditionals;
        $definition->setInstanceofConditionals($instanceof);

        return $definition;
    }

    private function bindArguments(Definition $definition, \ReflectionFunctionAbstract $constructor): Definition
    {
        $bindings = $definition->getBindings();
        foreach ($constructor->getParameters() as $reflectionParameter) {
            $argument = null;
            foreach ($reflectionParameter->getAttributes() as $attribute) {
                if (!$configurator = $this->argumentConfigurators[$attribute->getName()] ?? null) {
                    continue;
                }
                if ($argument) {
                    throw new LogicException(sprintf('Cannot autoconfigure argument "$%s": More than one autoconfigurable attribute found.', $reflectionParameter->getName()));
                }
                $argument = $configurator($attribute->newInstance());
            }
            if ($argument) {
                $bindings['$'.$reflectionParameter->getName()] = new BoundArgument($argument);
            }
        }

        return $definition->setBindings($bindings);
    }
}
