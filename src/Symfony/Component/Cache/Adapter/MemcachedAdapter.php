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

use Symfony\Component\Cache\Adapter\Client\MemcachedClient;

/**
 * @author Rob Frawley 2nd <rmf@src.run>
 */
class MemcachedAdapter extends AbstractAdapter
{
    private $client;

    public function __construct(\Memcached $client, $namespace = '', $defaultLifetime = 0)
    {
        parent::__construct($namespace, $defaultLifetime);
        $this->client = $client;
    }

    /**
     * @param string[] $servers
     * @param mixed[]  $options
     *
     * @return \Memcached
     */
    public static function createConnection($servers = array(), array $options = array())
    {
        return MemcachedClient::create($servers, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        return $this->client->setMulti($values, $lifetime) && $this->client->getResultCode() === \Memcached::RES_SUCCESS;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        return $this->client->getMulti($ids);
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        return $this->client->get($id) !== false || $this->client->getResultCode() === \Memcached::RES_SUCCESS;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        $toDelete = count($ids);
        foreach ($this->client->deleteMulti($ids) as $result) {
            if (\Memcached::RES_SUCCESS === $result || \Memcached::RES_NOTFOUND === $result) {
                --$toDelete;
            }
        }

        return 0 === $toDelete;
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        return $this->client->flush();
    }

    public function __destruct()
    {
        parent::__destruct();

        if (!$this->client->isPersistent() && method_exists($this->client, 'flushBuffers')) {
            $this->client->flushBuffers();
        }
    }
}
