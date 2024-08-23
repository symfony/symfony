<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CallableWrapper\DependencyInjection;

use Symfony\Component\CallableWrapper\Resolver\CallableWrapperResolver;
use Symfony\Component\CallableWrapper\Resolver\CallableWrapperResolverInterface;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
final readonly class CallableWrappersPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('callable_wrapper')) {
            return;
        }

        $tagName = new TaggedIteratorArgument('callable_wrapper', needsIndexes: true);
        $wrappers = $this->findAndSortTaggedServices($tagName, $container);

        $resolver = (new Definition(CallableWrapperResolver::class))
            ->addArgument(ServiceLocatorTagPass::map($wrappers))
            ->addTag('container.service_locator');

        $id = '.service_locator.'.ContainerBuilder::hash($resolver);
        $container->setDefinition($id, $resolver);

        $container->setAlias(CallableWrapperResolverInterface::class, $id);
    }
}
