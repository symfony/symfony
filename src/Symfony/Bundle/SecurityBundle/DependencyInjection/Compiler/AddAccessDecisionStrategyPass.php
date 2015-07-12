<?php

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AddAccessDecisionStrategyPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('security.access.decision_manager')) {
            return;
        }

        $strategies = array();
        foreach ($container->findTaggedServiceIds('security.access_strategy') as $id => $attributes) {
            $strategyName = isset($attributes[0]['strategy']) ? $attributes[0]['strategy'] : 0;
            $strategies[$strategyName] = new Reference($id);
        }

        if (!$strategies) {
            throw new LogicException('No access decision strategies found. You need to tag at least one with "security.access_strategy"');
        }

        $container->getDefinition('security.access.decision_manager')->addMethodCall('setStrategies',  $strategies);
    }

}