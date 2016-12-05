<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Rob Frawley 2nd <rmf@src.run>
 */
class MemcachedAdapter extends AbstractAdapter
{
    use MemcacheAdapterTrait;

    /**
     * Construct adapter by passing a \Memcached instance and an optional namespace and default cache entry ttl.
     *
     * @param \Memcached  $client
     * @param string|null $namespace
     * @param int         $defaultLifetime
     */
    public function __construct(\Memcached $client, $namespace = '', $defaultLifetime = 0)
    {
        parent::__construct($namespace, $defaultLifetime);
        $this->client = $client;
    }

    /**
     * Factory creation method that provides an instance of this adapter with a\Memcached client instantiated and setup.
     *
     * Valid DSN values include the following:
     *  - memcached://localhost                  : Specifies only the host (defaults used for port and weight)
     *  - memcached://example.com:1234           : Specifies host and port (defaults weight)
     *  - memcached://example.com:1234?weight=50 : Specifies host, port, and weight (no defaults used)
     *
     * Valid options include any client constants, as described in the PHP manual:
     *  - http://php.net/manual/en/memcached.constants.php
     *
     * Options are expected to be passed as an associative array with indexes of the option type with coorosponding
     * values as the option assignment.
     *
     * @param string|null $dsn
     * @param array       $opts
     * @param string|null $persistentId
     *
     * @return MemcachedAdapter
     */
    public static function create($dsn = null, array $opts = array(), $persistentId = null)
    {
        if (!extension_loaded('memcached')) {
            throw new InvalidArgumentException('Failed to create Memcache client due to missing "memcached" extension.');
        }

        $adapter = new static(new \Memcached($persistentId));
        $adapter->setup($dsn ? array($dsn) : array(), $opts);

        return $adapter;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        return $this->client->setMulti($values, $lifetime)
            && $this->isPreviousClientActionSuccessful();
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        foreach ($this->client->getMulti($ids) as $id => $val) {
            yield $id => $val;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        return $this->client->get($id) !== false
            && $this->client->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        $toDelete = count($ids);
        foreach ((array) $this->client->deleteMulti($ids) as $result) {
            if (true === $result || \Memcached::RES_NOTFOUND === $result) {
                --$toDelete;
            }
        }

        return 0 === $toDelete;
    }

    private function addServer($dsn)
    {
        list($host, $port, $weight) = $this->dsnExtract($dsn);

        return $this->isServerInClientPool($host, $port)
            || ($this->client->addServer($host, $port, $weight)
                && $this->isPreviousClientActionSuccessful());
    }

    private function setOption($opt, $val)
    {
        list($opt, $val) = $this->optionSanitize($opt, $val);

        $restore = error_reporting(~E_ALL);
        $success = $this->client->setOption($opt, $val);
        error_reporting($restore);

        return $success && $this->isPreviousClientActionSuccessful();
    }

    private function getIdsByPrefix($namespace)
    {
        if (false === $ids = $this->client->getAllKeys()) {
            return false;
        }

        return array_filter((array) $ids, function ($id) use ($namespace) {
            return 0 === strpos($id, $namespace);
        });
    }

    private function optionSanitize($opt, $val)
    {
        if (false === filter_var($opt = $this->optionResolve($opt), FILTER_VALIDATE_INT)) {
            throw new InvalidArgumentException(sprintf('Invalid memcached option type: %s (expects an int or a resolvable client constant)', $opt));
        }

        if (false === filter_var($val = $this->optionResolve($val), FILTER_VALIDATE_INT) &&
            null  === filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) {
            throw new InvalidArgumentException(sprintf('Invalid memcached option value: %s (expects an int, a bool, or a resolvable client constant)', $val));
        }

        return array($opt, $val);
    }

    private function optionResolve($val)
    {
        return defined($constant = '\Memcached::'.strtoupper($val)) ? constant($constant) : $val;
    }

    private function isPreviousClientActionSuccessful()
    {
        return $this->client->getResultCode() === \Memcached::RES_SUCCESS;
    }

    protected function isServerInClientPool($host, $port)
    {
        return (bool) array_filter($this->client->getServerList(), function ($srv) use ($host, $port) {
            return $host === array_shift($srv) && $port === array_shift($srv);
        });
    }
}
