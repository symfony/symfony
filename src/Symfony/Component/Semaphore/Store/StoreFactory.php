<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Store;

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Traits\RedisClusterProxy;
use Symfony\Component\Cache\Traits\RedisProxy;
use Symfony\Component\Semaphore\Exception\InvalidArgumentException;
use Symfony\Component\Semaphore\PersistingStoreInterface;

/**
 * StoreFactory create stores and connections.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class StoreFactory
{
    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|RedisProxy|RedisClusterProxy|string $connection Connection or DSN or Store short name
     */
    public static function createStore($connection): PersistingStoreInterface
    {
        if (!\is_string($connection) && !\is_object($connection)) {
            throw new \TypeError(sprintf('Argument 1 passed to "%s()" must be a string or a connection object, "%s" given.', __METHOD__, \gettype($connection)));
        }

        switch (true) {
            case $connection instanceof \Redis:
            case $connection instanceof \RedisArray:
            case $connection instanceof \RedisCluster:
            case $connection instanceof \Predis\ClientInterface:
            case $connection instanceof RedisProxy:
            case $connection instanceof RedisClusterProxy:
                return new RedisStore($connection);

            case !\is_string($connection):
                throw new InvalidArgumentException(sprintf('Unsupported Connection: "%s".', \get_class($connection)));
            case 0 === strpos($connection, 'redis://'):
            case 0 === strpos($connection, 'rediss://'):
                if (!class_exists(AbstractAdapter::class)) {
                    throw new InvalidArgumentException(sprintf('Unsupported DSN "%s". Try running "composer require symfony/cache".', $connection));
                }
                $connection = AbstractAdapter::createConnection($connection, ['lazy' => true]);

                return new RedisStore($connection);
        }

        throw new InvalidArgumentException(sprintf('Unsupported Connection: "%s".', $connection));
    }
}
