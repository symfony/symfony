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
 * MemcachedSessionHandler.
 *
 * Memcached based session storage handler based on the Memcached class
 * provided by the PHP memcached extension.
 *
 * @see http://php.net/memcached
 *
 * @author Drak <drak@zikula.org>
 */
class MemcachedSessionHandler implements \SessionHandlerInterface
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
     * Key prefix for shared environments.
     *
     * @var string
     */
    private $prefix;

    /**
     * Constructor.
     *
     * @param \Memcached $memcached        A \Memcached instance
     * @param array      $memcachedOptions An associative array of Memcached options
     */
    public function __construct(\Memcached $memcached, array $memcachedOptions = array())
    {
        $this->memcached = $memcached;

        $memcachedOptions['expiretime'] = isset($memcachedOptions['expiretime']) ? (int) $memcachedOptions['expiretime'] : 86400;
        $this->prefix = isset($memcachedOptions['prefix']) ? $memcachedOptions['prefix'] : 'sf2s';

        $this->memcachedOptions = $memcachedOptions;
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
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read($sessionId)
    {
        return $this->memcached->get($this->prefix.$sessionId) ?: '';
    }

    /**
     * {@inheritDoc}
     */
    public function write($sessionId, $data)
    {
        return $this->memcached->set($this->prefix.$sessionId, $data, time() + $this->memcachedOptions['expiretime']);
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($sessionId)
    {
        return $this->memcached->delete($this->prefix.$sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function gc($lifetime)
    {
        // not required here because memcached will auto expire the records anyhow.
        return true;
    }
}
