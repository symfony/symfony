<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gathers and configures the console argument value resolvers.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class ConsoleArgumentValueResolverPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('console.argument_resolver')) {
            return;
        }

        $definitions = $container->getDefinitions();
        $namedResolvers = $this->findAndSortTaggedServices(new TaggedIteratorArgument('console.targeted_value_resolver', 'name', needsIndexes: true), $container);
        $resolvers = $this->findAndSortTaggedServices(new TaggedIteratorArgument('console.argument_value_resolver', 'name', needsIndexes: true), $container);

        foreach ($resolvers as $name => $resolver) {
            if ($definitions[(string) $resolver]->hasTag('console.targeted_value_resolver')) {
                unset($resolvers[$name]);
            } else {
                $namedResolvers[$name] ??= clone $resolver;
            }
        }

        $container
            ->getDefinition('console.argument_resolver')
            ->replaceArgument(0, new IteratorArgument(array_values($resolvers)))
            ->setArgument(1, new ServiceLocatorArgument($namedResolvers))
        ;
    }
}
