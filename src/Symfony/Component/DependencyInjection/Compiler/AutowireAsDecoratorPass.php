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
use Symfony\Component\DependencyInjection\Attribute\AsTagDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Reads #[AsDecorator] and #[AsTagDecorator] attributes on definitions that are autowired
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
        $decoratorAttributes = $reflectionClass->getAttributes(AsDecorator::class, \ReflectionAttribute::IS_INSTANCEOF);
        $tagDecoratorAttributes = $reflectionClass->getAttributes(AsTagDecorator::class, \ReflectionAttribute::IS_INSTANCEOF);

        if (!$decoratorAttributes && !$tagDecoratorAttributes) {
            return;
        }

        if (1 === \count($decoratorAttributes) && !$tagDecoratorAttributes) {
            $attribute = $decoratorAttributes[0]->newInstance();
            $definition->setDecoratedService($attribute->decorates, null, $attribute->priority, $attribute->onInvalid);

            return;
        }

        foreach ($decoratorAttributes as $attribute) {
            $attribute = $attribute->newInstance();

            $clonedDefinition = clone $definition;
            $clonedDefinition->setDecoratedService($attribute->decorates, null, $attribute->priority, $attribute->onInvalid);
            $container->setDefinition(\sprintf('.decorator.%s.%s', $attribute->decorates, $id), $clonedDefinition);
        }

        foreach ($tagDecoratorAttributes as $attribute) {
            $attribute = $attribute->newInstance();

            $clonedDefinition = clone $definition;
            $tagAttributes = [
                'decorates_tag' => $attribute->tag,
                'priority' => $attribute->priority,
            ];

            if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $attribute->onInvalid) {
                $tagAttributes['on_invalid'] = $attribute->onInvalid;
            }

            $clonedDefinition->addResourceTag('container.tag_decorator', $tagAttributes);
            $container->setDefinition(\sprintf('.tag_decorator.%s.%s', $attribute->tag, $id), $clonedDefinition);
        }

        $container->removeDefinition($id);
    }
}
