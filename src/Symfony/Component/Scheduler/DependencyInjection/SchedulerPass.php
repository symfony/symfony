<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\DependencyInjection;

use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Messenger\ScheduleTransportFactory;
use Symfony\Contracts\Cache\CacheInterface;

class SchedulerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $usedCachePools = [];
        $usedLockFactories = [];

        foreach ($container->findTaggedServiceIds('messenger.receiver') as $id => $tags) {
            $transport = $container->getDefinition($id);
            [$dsn, $options] = $transport->getArguments();
            if (!ScheduleTransportFactory::isSupported($dsn)) {
                continue;
            }

            if (\is_string($options['cache'] ?? null) && $options['cache']) {
                $usedCachePools[] = $options['cache'];
            }
            if (\is_string($options['lock'] ?? null) && $options['lock']) {
                $usedLockFactories[] = $options['lock'];
            }
            if (\is_array($options['lock'] ?? null) &&
                \is_string($options['lock']['resource'] ?? null) &&
                $options['lock']['resource']
            ) {
                $usedLockFactories[] = $options['lock']['resource'];
            }
        }

        if ($usedCachePools) {
            $this->locateCachePools($container, $usedCachePools);
        }
        if ($usedLockFactories) {
            $this->locateLockFactories($container, $usedLockFactories);
        }
    }

    /**
     * @param string[] $cachePools
     */
    private function locateCachePools(ContainerBuilder $container, array $cachePools): void
    {
        if (!class_exists(CacheItem::class)) {
            throw new \LogicException('You cannot use the "cache" option if the Cache Component is not available. Try running "composer require symfony/cache".');
        }

        $references = [];
        foreach (array_unique($cachePools) as $name) {
            if (!$this->isServiceInstanceOf($container, $id = $name, CacheInterface::class) &&
                !$this->isServiceInstanceOf($container, $id = 'cache.'.$name, CacheInterface::class)
            ) {
                throw new RuntimeException(sprintf('The cache pool "%s" does not exist.', $name));
            }

            $references[$name] = new Reference($id);
        }

        $container->getDefinition('scheduler.cache_locator')
            ->replaceArgument(0, $references);
    }

    /**
     * @param string[] $lockFactories
     */
    private function locateLockFactories(ContainerBuilder $container, array $lockFactories): void
    {
        if (!class_exists(LockFactory::class)) {
            throw new \LogicException('You cannot use the "lock" option if the Lock Component is not available. Try running "composer require symfony/lock".');
        }

        $references = [];
        foreach (array_unique($lockFactories) as $name) {
            if (!$this->isServiceInstanceOf($container, $id = $name, LockFactory::class) &&
                !$this->isServiceInstanceOf($container, $id = 'lock.'.$name.'.factory', LockFactory::class)
            ) {
                throw new RuntimeException(sprintf('The lock resource "%s" does not exist.', $name));
            }

            $references[$name] = new Reference($id);
        }

        $container->getDefinition('scheduler.lock_locator')
            ->replaceArgument(0, $references);
    }

    private function isServiceInstanceOf(ContainerBuilder $container, string $serviceId, string $className): bool
    {
        if (!$container->hasDefinition($serviceId)) {
            return false;
        }

        while (true) {
            $definition = $container->getDefinition($serviceId);
            if (!$definition->getClass() && $definition instanceof ChildDefinition) {
                $serviceId = $definition->getParent();

                continue;
            }

            return $definition->getClass() && is_a($definition->getClass(), $className, true);
        }
    }
}
