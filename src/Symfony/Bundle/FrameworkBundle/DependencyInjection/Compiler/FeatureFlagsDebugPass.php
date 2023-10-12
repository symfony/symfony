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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\FeatureFlags\Debug\TraceableFeatureChecker;
use Symfony\Component\FeatureFlags\Debug\TraceableStrategy;

final class FeatureFlagsDebugPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('feature_flags.data_collector')) {
            return;
        }

        foreach ($container->findTaggedServiceIds('feature_flags.feature_strategy') as $serviceId => $tags) {
            $container->register('debug.'.$serviceId, TraceableStrategy::class)
                ->setDecoratedService($serviceId)
                ->setArguments([
                    '$strategy' => new Reference('.inner'),
                    '$strategyId' => $serviceId,
                    '$dataCollector' => new Reference('feature_flags.data_collector'),
                ])
            ;
        }
    }
}
