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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class TagDecoratorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedResourceIds('container.tag_decorator', false) as $id => $tags) {
            $definition = $container->getDefinition($id);

            foreach ($tags as $tag) {
                if (!$decoratesTag = $tag['decorates_tag'] ?? null) {
                    continue;
                }

                $priority = $tag['priority'] ?? 0;
                $invalidBehavior = $tag['on_invalid'] ?? ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
                $taggedServices = $container->findTaggedServiceIds($decoratesTag);

                if (!$taggedServices) {
                    if (ContainerInterface::IGNORE_ON_INVALID_REFERENCE === $invalidBehavior) {
                        continue;
                    }

                    if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE === $invalidBehavior) {
                        throw new ServiceNotFoundException($decoratesTag, $id);
                    }
                }

                foreach ($taggedServices as $taggedServiceId => $_) {
                    $clonedDefinition = clone $definition;
                    $clonedDefinition->clearTag('container.tag_decorator');
                    $clonedDefinition->clearTag('container.excluded');
                    $clonedDefinition->setDecoratedService($taggedServiceId, null, $priority, $invalidBehavior);
                    $decoratorId = \sprintf('.decorator.%s.%s', $taggedServiceId, $id);
                    $container->setDefinition($decoratorId, $clonedDefinition);
                }
            }

            $container->removeDefinition($id);
        }
    }
}
