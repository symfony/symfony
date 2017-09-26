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
use Symfony\Component\Dsn\Factory\MemcachedFactory;
use Symfony\Component\Dsn\Factory\RedisFactory;

/**
 * Factory for undetermined DSN.
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

        throw new InvalidArgumentException(sprintf('Unsupported DSN: %s.', $dsn));
    }

    /**
     * Create a connection for a given DSN.
     *
     * @param string $dsn
     * @param array  $options
     *
     * @return mixed
     */
    public static function create($dsn, array $options = array())
    {
        switch (static::getType($dsn)) {
            case static::TYPE_REDIS:
                return RedisFactory::create($dsn, $options);
            case static::TYPE_MEMCACHED:
                return MemcachedFactory::create($dsn, $options);
            default:
                throw new InvalidArgumentException(sprintf('Unsupported DSN: %s.', $dsn));
        }
    }
}
