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
class MemcacheAdapter extends AbstractAdapter
{
    use MemcacheAdapterTrait;

    /**
     * Construct adapter by passing a \Memcache instance and an optional namespace and default cache entry ttl.
     *
     * @param \Memcache   $client
     * @param string|null $namespace
     * @param int         $defaultLifetime
     */
    public function __construct(\Memcache $client, $namespace = '', $defaultLifetime = 0)
    {
        parent::__construct($namespace, $defaultLifetime);
        $this->client = $client;
    }

    /**
     * Factory creation method that provides an instance of this adapter with a\Memcache client instantiated and setup.
     *
     * Valid DSN values include the following:
     *  - memcache://localhost                  : Specifies only the host (defaults used for port and weight)
     *  - memcache://example.com:1234           : Specifies host and port (defaults weight)
     *  - memcache://example.com:1234?weight=50 : Specifies host, port, and weight (no defaults used)
     *
     * @param string|null $dsn
     *
     * @return MemcacheAdapter
     */
    public static function create($dsn = null)
    {
        if (!extension_loaded('memcache') || !version_compare(phpversion('memcache'), '3.0.8', '>')) {
            throw new InvalidArgumentException('Failed to create memcache client due to missing "memcache" extension or version <3.0.9.');
        }

        $adapter = new static(new \Memcache());
        $adapter->setup($dsn ? array($dsn) : array());

        return $adapter;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        $result = true;

        foreach ($values as $id => $val) {
            $result = $this->client->set($id, $val, null, $lifetime) && $result;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        foreach ($this->client->get($ids) as $id => $val) {
            yield $id => $val;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        return $this->client->get($id) !== false;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        $remaining = array_filter($ids, function ($id) {
            return false !== $this->client->get($id) && false === $this->client->delete($id);
        });

        return 0 === count($remaining);
    }

    private function getIdsByPrefix($namespace)
    {
        $ids = array();
        foreach ($this->client->getExtendedStats('slabs') as $slabGroup) {
            foreach ($slabGroup as $slabId => $slabMetadata) {
                if (!is_array($slabMetadata)) {
                    continue;
                }
                foreach ($this->client->getExtendedStats('cachedump', (int) $slabId, 1000) as $slabIds) {
                    if (is_array($slabIds)) {
                        $ids = array_merge($ids, array_keys($slabIds));
                    }
                }
            }
        }

        return array_filter((array) $ids, function ($id) use ($namespace) {
            return 0 === strpos($id, $namespace);
        });
    }

    private function addServer($dsn)
    {
        list($host, $port, $weight) = $this->dsnExtract($dsn);

        return $this->isServerInClientPool($host, $port)
            || $this->client->addServer($host, $port, false, $weight);
    }

    private function setOption($opt, $val)
    {
        return true;
    }

    private function isServerInClientPool($host, $port)
    {
        $restore = error_reporting(~E_ALL);
        $srvStat = $this->client->getServerStatus($host, $port);
        error_reporting($restore);

        return 1 === $srvStat;
    }
}
