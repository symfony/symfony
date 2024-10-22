<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator\DependencyInjection;

use Symfony\Component\Decorator\Resolver\DecoratorResolver;
use Symfony\Component\Decorator\Resolver\DecoratorResolverInterface;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
final readonly class DecoratorsPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('decorator.callable_decorator')) {
            return;
        }

        $tagName = new TaggedIteratorArgument('decorator', needsIndexes: true);
        $decorators = $this->findAndSortTaggedServices($tagName, $container);

        $resolver = (new Definition(DecoratorResolver::class))
            ->addArgument(ServiceLocatorTagPass::map($decorators))
            ->addTag('container.service_locator');

        $id = '.service_locator.'.ContainerBuilder::hash($resolver);
        $container->setDefinition($id, $resolver);

        $container->setAlias(DecoratorResolverInterface::class, $id);
    }
}
