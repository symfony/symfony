<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dsn;

use Symfony\Component\Dsn\Exception\InvalidArgumentException;
use Symfony\Component\Dsn\Factory\MemcachedConnectionFactory;
use Symfony\Component\Dsn\Factory\RedisConnectionFactory;

/**
 * Factory for undetermined Dsn.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class ConnectionFactory
{
    const TYPE_REDIS = 'redis';
    const TYPE_MEMCACHED = 'memcached';

    /**
     * @param string $dsn
     *
     * @return string
     */
    public static function getType($dsn)
    {
        if (!is_string($dsn)) {
            throw new InvalidArgumentException(sprintf('The %s() method expect argument #1 to be string, %s given.', __METHOD__, gettype($dsn)));
        }
        if (0 === strpos($dsn, 'redis://')) {
            return static::TYPE_REDIS;
        }
        if (0 === strpos($dsn, 'memcached://')) {
            return static::TYPE_MEMCACHED;
        }

        throw new InvalidArgumentException(sprintf('Unsupported Dsn: %s.', $dsn));
    }

    /**
     * Create a connection for a redis Dsn.
     *
     * @param string $dsn
     * @param array  $options
     *
     * @return mixed
     */
    public static function createConnection($dsn, array $options = array())
    {
        switch (static::getType($dsn)) {
            case static::TYPE_REDIS:
                return RedisConnectionFactory::createConnection($dsn, $options);
            case static::TYPE_MEMCACHED:
                return MemcachedConnectionFactory::createConnection($dsn, $options);
            default:
                throw new InvalidArgumentException(sprintf('Unsupported Dsn: %s.', $dsn));
        }
    }
}
