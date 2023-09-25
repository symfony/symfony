<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureToggleBundle\DependencyInjection\CompilerPass;

use Symfony\Bundle\FeatureToggleBundle\Debug\TraceableFeatureChecker;
use Symfony\Bundle\FeatureToggleBundle\Debug\TraceableStrategy;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class DebugPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('feature_toggle.data_collector')) {
            return;
        }

        $container->register('debug.feature_toggle.feature_checker', TraceableFeatureChecker::class)
            ->setDecoratedService('feature_toggle.feature_checker')
            ->setArguments([
                '$featureChecker' => new Reference('.inner'),
                '$dataCollector' => new Reference('feature_toggle.data_collector'),
            ])
        ;

        foreach ($container->findTaggedServiceIds('feature_toggle.feature_strategy') as $serviceId => $tags) {
            $container->register('debug.'.$serviceId, TraceableStrategy::class)
                ->setDecoratedService($serviceId)
                ->setArguments([
                    '$strategy' => new Reference('.inner'),
                    '$strategyId' => $serviceId,
                    '$dataCollector' => new Reference('feature_toggle.data_collector'),
                ])
            ;
        }
    }
}
