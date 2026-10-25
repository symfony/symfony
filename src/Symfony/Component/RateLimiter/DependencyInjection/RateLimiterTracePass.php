<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\DependencyInjection;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\Debug\TraceableRateLimiterBuilder;
use Symfony\Component\RateLimiter\Debug\TraceableRateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/** @internal */
final class RateLimiterTracePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('rate_limiter.data_collector')) {
            return;
        }

        $collector = new Reference('rate_limiter.data_collector');

        $container->register('debug.limiter_builder', TraceableRateLimiterBuilder::class)
            ->setDecoratedService('limiter_builder', invalidBehavior: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->setArguments([new Reference('.inner'), $collector]);

        foreach ($container->findTaggedServiceIds('rate_limiter') as $id => $tags) {
            $container->register('debug.'.$id, TraceableRateLimiterFactory::class)
                ->setDecoratedService($id)
                ->setArguments([new Reference('.inner'), $tags[0]['name'] ?? $id, $collector, $this->getPolicy($container->getDefinition($id))]);
        }
    }

    private function getPolicy(Definition $definition): mixed
    {
        if (CompoundRateLimiterFactory::class === $definition->getClass()) {
            return 'compound';
        }

        $isStandard = RateLimiterFactory::class === $definition->getClass()
            || ($definition instanceof ChildDefinition && 'limiter' === $definition->getParent());

        if (!$isStandard) {
            return null;
        }

        try {
            $config = $definition->getArgument(0);
        } catch (\OutOfBoundsException) {
            return null;
        }

        return \is_array($config) ? $config['policy'] ?? null : null;
    }
}
