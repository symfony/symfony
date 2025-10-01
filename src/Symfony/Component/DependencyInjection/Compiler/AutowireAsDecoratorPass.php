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

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Reads #[AsDecorator] attributes on definitions that are autowired
 * and don't have the "container.ignore_attributes" tag.
 */
final class AutowireAsDecoratorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($this->accept($definition) && $reflectionClass = $container->getReflectionClass($definition->getClass(), false)) {
                $this->processClass($id, $container, $definition, $reflectionClass);
            }
        }
    }

    private function accept(Definition $definition): bool
    {
        return !$definition->hasTag('container.ignore_attributes') && $definition->isAutowired();
    }

    private function processClass(string $id, ContainerBuilder $container, Definition $definition, \ReflectionClass $reflectionClass): void
    {
        if (!$attributes = $reflectionClass->getAttributes(AsDecorator::class, \ReflectionAttribute::IS_INSTANCEOF)) {
            return;
        }

        if (1 === \count($attributes)) {
            $attribute = $attributes[0]->newInstance();
            $definition->setDecoratedService($attribute->decorates, null, $attribute->priority, $attribute->onInvalid);

            return;
        }

        foreach ($attributes as $attribute) {
            $attribute = $attribute->newInstance();

            $definition = clone $definition;
            $definition->setDecoratedService($attribute->decorates, null, $attribute->priority, $attribute->onInvalid);
            $container->setDefinition(\sprintf('.decorator.%s.%s', $attribute->decorates, $id), $definition);
        }

        $container->removeDefinition($id);
    }
}
