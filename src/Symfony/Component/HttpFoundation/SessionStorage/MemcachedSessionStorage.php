<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\AttributeBagInterface;
use Symfony\Component\HttpFoundation\FlashBagInterface;

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
     * @var Memcached
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
     * @param AttributeBagInterface $attributes       An AttributeBagInterface instance, (defaults null for default AttributeBag)
     * @param FlashBagInterface     $flashes          A FlashBagInterface instance (defaults null for default FlashBag)
     *
     * @see AbstractSessionStorage::__construct()
     */
    public function __construct(\Memcached $memcache, array $memcachedOptions = array(), array $options = array(), AttributeBagInterface $attributes = null, FlashBagInterface $flashes = null)
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

        $this->memcached->setOption(\Memcached::OPT_PREFIX_KEY, isset($memcachedOptions['prefix']) ? $memcachedOption['prefix'] : 'sf2s');

        $this->memcacheOptions = $memcachedOptions;

        parent::__construct($attributes, $flashes, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function sessionOpen($savePath, $sessionName)
    {
        foreach ($this->memcachedOptions['serverpool'] as $server) {
            $this->addServer($server);
        }

        return true;
    }

    /**
     * Close session.
     *
     * @return boolean
     */
    public function sessionClose()
    {
        return $this->memcached->close();
    }

    /**
     * {@inheritdoc}
     */
    public function sessionRead($sessionId)
    {
        return $this->memcached->get($this->prefix.$sessionId) ?: '';
    }

    /**
     * {@inheritdoc}
     */
    public function sessionWrite($sessionId, $data)
    {
        return $this->memcached->set($this->prefix.$sessionId, $data, false, $this->memcachedOptions['expiretime']);
    }

    /**
     * {@inheritdoc}
     */
    public function sessionDestroy($sessionId)
    {
        return $this->memcached->delete($this->prefix.$sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function sessionGc($lifetime)
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
