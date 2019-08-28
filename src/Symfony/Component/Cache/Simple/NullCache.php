<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Simple;

use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Contracts\Cache\CacheInterface;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.3, use "%s" and type-hint for "%s" instead.', NullCache::class, NullAdapter::class, CacheInterface::class), E_USER_DEPRECATED);

/**
 * @deprecated since Symfony 4.3, use NullAdapter and type-hint for CacheInterface instead.
 */
class NullCache implements Psr16CacheInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        foreach ($keys as $key) {
            yield $key => $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        return false;
    }
}
