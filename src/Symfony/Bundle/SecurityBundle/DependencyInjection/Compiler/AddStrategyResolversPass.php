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

use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\Security\Core\Authorization\StrategyResolverInterface;

class AddStrategyResolversPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('security.access.decision_manager')) {
            return;
        }

        $strategyResolverReferences = $this->findAndSortTaggedServices('security.strategy_resolver', $container);
        foreach ($strategyResolverReferences as $strategyResolverReference) {
            $strategyResolverServiceId = $strategyResolverReference->__toString();
            $class = $container->getDefinition($strategyResolverServiceId)->getClass();
            if (!is_subclass_of($class, StrategyResolverInterface::class)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $strategyResolverServiceId, StrategyResolverInterface::class));
            }
        }

        $container->getDefinition('security.access.decision_manager')->replaceArgument(4, $strategyResolverReferences);
    }
}
