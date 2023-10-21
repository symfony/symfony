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

use Relay\Relay;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Semaphore\Exception\InvalidArgumentException;
use Symfony\Component\Semaphore\PersistingStoreInterface;

/**
 * StoreFactory create stores and connections.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class StoreFactory
{
    public static function createStore(#[\SensitiveParameter] object|string $connection): PersistingStoreInterface
    {
        switch (true) {
            case $connection instanceof \Redis:
            case $connection instanceof Relay:
            case $connection instanceof \RedisArray:
            case $connection instanceof \RedisCluster:
            case $connection instanceof \Predis\ClientInterface:
                return new RedisStore($connection);

            case !\is_string($connection):
                throw new InvalidArgumentException(sprintf('Unsupported Connection: "%s".', $connection::class));
            case str_starts_with($connection, 'redis://'):
            case str_starts_with($connection, 'rediss://'):
                if (!class_exists(AbstractAdapter::class)) {
                    throw new InvalidArgumentException('Unsupported Redis DSN. Try running "composer require symfony/cache".');
                }
                $connection = AbstractAdapter::createConnection($connection, ['lazy' => true]);

                return new RedisStore($connection);
        }

        throw new InvalidArgumentException(sprintf('Unsupported Connection: "%s".', $connection));
    }
}
