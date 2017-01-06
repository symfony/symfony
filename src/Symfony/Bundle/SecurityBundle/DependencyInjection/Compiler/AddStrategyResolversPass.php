<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class AddStrategyResolversPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('security.access.decision_manager')) {
            return;
        }

        $strategyResolvers = new \SplPriorityQueue();
        foreach ($container->findTaggedServiceIds('security.strategy_resolver') as $id => $attributes) {
            $class = $container->getDefinition($id)->getClass();
            $interface = 'Symfony\Component\Security\Core\Authorization\StrategyResolverInterface';
            if (!is_subclass_of($class, $interface)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
            }

            $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
            $strategyResolvers->insert(new Reference($id), $priority);
        }

        $strategyResolvers = iterator_to_array($strategyResolvers);
        ksort($strategyResolvers);

        $container->getDefinition('security.access.decision_manager')->replaceArgument(4, array_values($strategyResolvers));
    }
}
