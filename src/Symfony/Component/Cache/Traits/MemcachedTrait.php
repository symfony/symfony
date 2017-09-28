<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Dsn\Factory\MemcachedFactory;

/**
 * @author Rob Frawley 2nd <rmf@src.run>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait MemcachedTrait
{
    private $client;
    private $lazyClient;

    public static function isSupported()
    {
        return extension_loaded('memcached') && version_compare(phpversion('memcached'), '2.2.0', '>=');
    }

    private function init(\Memcached $client, $namespace, $defaultLifetime)
    {
        if (!static::isSupported()) {
            throw new CacheException('Memcached >= 2.2.0 is required');
        }
        if ('Memcached' === get_class($client)) {
            $opt = $client->getOption(\Memcached::OPT_SERIALIZER);
            if (\Memcached::SERIALIZER_PHP !== $opt && \Memcached::SERIALIZER_IGBINARY !== $opt) {
                throw new CacheException('MemcachedAdapter: "serializer" option must be "php" or "igbinary".');
            }
            $this->maxIdLength -= strlen($client->getOption(\Memcached::OPT_PREFIX_KEY));
            $this->client = $client;
        } else {
            $this->lazyClient = $client;
        }

        parent::__construct($namespace, $defaultLifetime);
        $this->enableVersioning();
    }

    /**
     * @see MemcachedFactory::create()
     */
    public static function createConnection($servers, array $options = array())
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.4 and will be removed in 4.0. Use the MemcachedFactory::create() method from Dsn component instead.', __METHOD__), E_USER_DEPRECATED);

        foreach ((array) $servers as $i => $dsn) {
            if (is_array($dsn)) {
                @trigger_error(sprintf('Passing an array of array to the %s() method is deprecated since version 3.4 and will be removed in 4.0. Use the MemcachedFactory::create() method from Dsn component instead with an array of Dsn instead.', __METHOD__), E_USER_DEPRECATED);

                $scheme = 'memcached://';
                $host = isset($dsn['host']) ? $dsn['host'] : '';
                $port = isset($dsn['port']) ? ':'.$dsn['port'] : '';
                $user = isset($dsn['user']) ? $dsn['user'] : '';
                $pass = isset($dsn['pass']) ? ':'.$dsn['pass'] : '';
                $pass = ($user || $pass) ? "$pass@" : '';
                $path = isset($dsn['path']) ? $dsn['path'] : '';
                $query = isset($dsn['query']) ? '?'.$dsn['query'] : '';
                $fragment = isset($dsn['fragment']) ? '#'.$dsn['fragment'] : '';

                $servers[$i] = "$scheme$user$pass$host$port$path$query$fragment";
            }
        }

        if (!static::isSupported()) {
            throw new CacheException('Memcached >= 2.2.0 is required');
        }

        try {
            return MemcachedFactory::create($servers, $options);
        } catch (\Symfony\Component\Dsn\Exception\InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        if ($lifetime && $lifetime > 30 * 86400) {
            $lifetime += time();
        }

        return $this->checkResultCode($this->getClient()->setMulti($values, $lifetime));
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        $unserializeCallbackHandler = ini_set('unserialize_callback_func', __CLASS__.'::handleUnserializeCallback');
        try {
            return $this->checkResultCode($this->getClient()->getMulti($ids));
        } catch (\Error $e) {
            throw new \ErrorException($e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine());
        } finally {
            ini_set('unserialize_callback_func', $unserializeCallbackHandler);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        return false !== $this->getClient()->get($id) || $this->checkResultCode(\Memcached::RES_SUCCESS === $this->client->getResultCode());
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        $ok = true;
        foreach ($this->checkResultCode($this->getClient()->deleteMulti($ids)) as $result) {
            if (\Memcached::RES_SUCCESS !== $result && \Memcached::RES_NOTFOUND !== $result) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        return false;
    }

    private function checkResultCode($result)
    {
        $code = $this->client->getResultCode();

        if (\Memcached::RES_SUCCESS === $code || \Memcached::RES_NOTFOUND === $code) {
            return $result;
        }

        throw new CacheException(sprintf('MemcachedAdapter client error: %s.', strtolower($this->client->getResultMessage())));
    }

    /**
     * @return \Memcached
     */
    private function getClient()
    {
        if ($this->client) {
            return $this->client;
        }

        $opt = $this->lazyClient->getOption(\Memcached::OPT_SERIALIZER);
        if (\Memcached::SERIALIZER_PHP !== $opt && \Memcached::SERIALIZER_IGBINARY !== $opt) {
            throw new CacheException('MemcachedAdapter: "serializer" option must be "php" or "igbinary".');
        }
        if ('' !== $prefix = (string) $this->lazyClient->getOption(\Memcached::OPT_PREFIX_KEY)) {
            throw new CacheException(sprintf('MemcachedAdapter: "prefix_key" option must be empty when using proxified connections, "%s" given.', $prefix));
        }

        return $this->client = $this->lazyClient;
    }
}
