<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage;

/**
 * MemcachedSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 */
class MemcachedSessionStorage extends AbstractSessionStorage implements SessionSaveHandlerInterface
{
    /**
     * Memcached driver.
     *
     * @var \Memcached
     */
    private $memcached;

    /**
     * Configuration options.
     *
     * @var array
     */
    private $memcachedOptions;

    /**
     * Constructor.
     *
     * @param \Memcached            $memcached        A \Memcached instance
     * @param array                 $memcachedOptions An associative array of Memcached options
     * @param array                 $options          Session configuration options.
     *
     * @see AbstractSessionStorage::__construct()
     */
    public function __construct(\Memcached $memcached, array $memcachedOptions = array(), array $options = array())
    {
        $this->memcached = $memcached;

        // defaults
        if (!isset($memcachedOptions['serverpool'])) {
            $memcachedOptions['serverpool'] = array(
                'host' => '127.0.0.1',
                'port' => 11211,
                'timeout' => 1,
                'persistent' => false,
                'weight' => 1);
        }

        $memcachedOptions['expiretime'] = isset($memcachedOptions['expiretime']) ? (int)$memcachedOptions['expiretime'] : 86400;

        $this->memcached->setOption(\Memcached::OPT_PREFIX_KEY, isset($memcachedOptions['prefix']) ? $memcachedOptions['prefix'] : 'sf2s');

        $this->memcacheOptions = $memcachedOptions;

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    public function openSession($savePath, $sessionName)
    {
        foreach ($this->memcachedOptions['serverpool'] as $server) {
            $this->addServer($server);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function closeSession()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function readSession($sessionId)
    {
        return $this->memcached->get($sessionId) ?: '';
    }

    /**
     * {@inheritdoc}
     */
    public function writeSession($sessionId, $data)
    {
        return $this->memcached->set($sessionId, $data, false, $this->memcachedOptions['expiretime']);
    }

    /**
     * {@inheritdoc}
     */
    public function destroySession($sessionId)
    {
        return $this->memcached->delete($sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function gcSession($lifetime)
    {
        // not required here because memcached will auto expire the records anyhow.
        return true;
    }

    /**
     * Adds a server to the memcached handler.
     *
     * @param array $server
     */
    protected function addServer(array $server)
    {
        if (array_key_exists('host', $server)) {
            throw new \InvalidArgumentException('host key must be set');
        }
        $server['port'] = isset($server['port']) ? (int)$server['port'] : 11211;
        $server['timeout'] = isset($server['timeout']) ? (int)$server['timeout'] : 1;
        $server['presistent'] = isset($server['presistent']) ? (bool)$server['presistent'] : false;
        $server['weight'] = isset($server['weight']) ? (bool)$server['weight'] : 1;
    }
}
