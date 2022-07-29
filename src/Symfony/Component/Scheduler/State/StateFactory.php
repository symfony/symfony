<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\State;

use Psr\Container\ContainerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Contracts\Cache\CacheInterface;

final class StateFactory implements StateFactoryInterface
{
    public function __construct(
        private readonly ContainerInterface $lockFactories,
        private readonly ContainerInterface $caches,
    ) {
    }

    public function create(string $scheduleName, array $options): StateInterface
    {
        $name = 'messenger.schedule.'.$scheduleName;
        $state = new State();

        if ($lock = $this->createLock($scheduleName, $name, $options)) {
            $state = new LockStateDecorator($state, $lock);
        }
        if ($cache = $this->createCache($scheduleName, $options)) {
            $state = new CacheStateDecorator($state, $cache, $name);
        }

        return $state;
    }

    private function createLock(string $scheduleName, string $resourceName, array $options): ?LockInterface
    {
        if (!($options['lock'] ?? false)) {
            return null;
        }

        if (\is_string($options['lock'])) {
            $options['lock'] = ['resource' => $options['lock']];
        }

        if (\is_array($options['lock']) && \is_string($resource = $options['lock']['resource'] ?? null)) {
            if (!$this->lockFactories->has($resource)) {
                throw new LogicException(sprintf('The lock resource "%s" does not exist.', $resource));
            }

            /** @var LockFactory $lockFactory */
            $lockFactory = $this->lockFactories->get($resource);

            $args = ['resource' => $resourceName];
            if (isset($options['lock']['ttl'])) {
                $args['ttl'] = (float) $options['lock']['ttl'];
            }
            if (isset($options['lock']['auto_release'])) {
                $args['autoRelease'] = (float) $options['lock']['auto_release'];
            }

            return $lockFactory->createLock(...$args);
        }

        throw new LogicException(sprintf('Invalid lock configuration for "%s" schedule.', $scheduleName));
    }

    private function createCache(string $scheduleName, array $options): ?CacheInterface
    {
        if (!($options['cache'] ?? false)) {
            return null;
        }

        if (\is_string($options['cache'])) {
            if (!$this->caches->has($options['cache'])) {
                throw new LogicException(sprintf('The cache pool "%s" does not exist.', $options['cache']));
            }

            return $this->caches->get($options['cache']);
        }

        throw new LogicException(sprintf('Invalid cache configuration for "%s" schedule.', $scheduleName));
    }
}
