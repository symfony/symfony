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
 * MemcacheSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 */
class MemcacheSessionStorage extends AbstractSessionStorage implements SessionHandlerInterface
{
    /**
     * Memcache driver.
     *
     * @var \Memcache
     */
    private $memcache;

    /**
     * Configuration options.
     *
     * @var array
     */
    private $memcacheOptions;

    /**
     * Key prefix for shared environments.
     *
     * @var string
     */
    private $prefix;

    /**
     * Constructor.
     *
     * @param \Memcache             $memcache        A \Memcache instance
     * @param array                 $memcacheOptions An associative array of Memcachge options
     * @param array                 $options         Session configuration options.
     *
     * @see AbstractSessionStorage::__construct()
     */
    public function __construct(\Memcache $memcache, array $memcacheOptions = array(), array $options = array())
    {
        $this->memcache = $memcache;

        // defaults
        if (!isset($memcacheOptions['serverpool'])) {
            $memcacheOptions['serverpool'] = array(
                'host' => '127.0.0.1',
                'port' => 11211,
                'timeout' => 1,
                'persistent' => false,
                'weight' => 1);
        }

        $memcacheOptions['expiretime'] = isset($memcacheOptions['expiretime']) ? (int)$memcacheOptions['expiretime'] : 86400;
        $this->prefix = isset($memcacheOptions['prefix']) ? $memcacheOptions['prefix'] : 'sf2s';

        $this->memcacheOptions = $memcacheOptions;

        parent::__construct($options);
    }

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

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        foreach ($this->memcacheOptions['serverpool'] as $server) {
            $this->memcache->addServer($server);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return $this->memcache->close();
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        return $this->memcache->get($this->prefix.$sessionId) ?: '';
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        return $this->memcache->set($this->prefix.$sessionId, $data, $this->memcacheOptions['expiretime']);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        return $this->memcache->delete($this->prefix.$sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function gc($lifetime)
    {
        // not required here because memcache will auto expire the records anyhow.
        return true;
    }
}
