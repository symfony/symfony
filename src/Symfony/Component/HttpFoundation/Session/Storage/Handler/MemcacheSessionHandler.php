<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage\Handler;

/**
 * MemcacheSessionHandler.
 *
 * @author Drak <drak@zikula.org>
 */
class MemcacheSessionHandler implements \SessionHandlerInterface
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
     * @param \Memcache $memcache        A \Memcache instance
     * @param array     $memcacheOptions An associative array of Memcache options
     */
    public function __construct(\Memcache $memcache, array $memcacheOptions = array())
    {
        $this->memcache = $memcache;

        $memcacheOptions['expiretime'] = isset($memcacheOptions['expiretime']) ? (int)$memcacheOptions['expiretime'] : 86400;
        $this->prefix = isset($memcacheOptions['prefix']) ? $memcacheOptions['prefix'] : 'sf2s';

        $this->memcacheOptions = $memcacheOptions;
    }

    /**
     * {@inheritDoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        return $this->memcache->close();
    }

    /**
     * {@inheritDoc}
     */
    public function read($sessionId)
    {
        return $this->memcache->get($this->prefix.$sessionId) ?: '';
    }

    /**
     * {@inheritDoc}
     */
    public function write($sessionId, $data)
    {
        return $this->memcache->set($this->prefix.$sessionId, $data, 0, time() + $this->memcacheOptions['expiretime']);
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($sessionId)
    {
        return $this->memcache->delete($this->prefix.$sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function gc($lifetime)
    {
        // not required here because memcache will auto expire the records anyhow.
        return true;
    }
}
