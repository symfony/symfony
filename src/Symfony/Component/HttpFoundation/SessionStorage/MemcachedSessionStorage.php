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

use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * MemcachedSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
class MemcachedSessionStorage extends AbstractSessionStorage implements SessionSaveHandlerInterface
{

    /**
     * Memcached driver.
     * 
     * @var Memcached
     */
    protected $memcached;
    
    /**
     * Constructor.
     *
     * @param FlashBagInterface $flashBag        FlashbagInterface instance.
     * @param \Memcached        $memcached       A \Memcached instance
     * @param array             $options         An associative array of session options
     * @param array             $memcachedOptions An associative array of Memcached options
     *
     * @throws \InvalidArgumentException When "db_table" option is not provided
     *
     * @see AbstractSessionStorage::__construct()
     */
    public function __construct(FlashBagInterface $flashBag, \Memcached $memcache, array $options = array(), array $memcachedOptions = array())
    {
        $this->memcached = $memcached;
        
        // defaults
        if (!isset($memcachedOptions['serverpool'])) {
            $memcachedOptions['serverpool'] = array('host' => '127.0.0.1', 'port' => 11211, 'timeout' => 1, 'persistent' => false, 'weight' => 1);
        }
        $memcachedOptions['expiretime'] = isset($memcachedOptions['expiretime']) ? (int)$memcachedOptions['expiretime'] : 86400;

        $this->memcacheOptions = $memcachedOptions;

        parent::__construct($flashBag, $options);
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
        $result = $this->memcached->get($sessionId);
        return ($result) ? $result : '';
    }

    /**
     * {@inheritdoc}
     */
    public function sessionWrite($sessionId, $data)
    {
        $this->memcached->set($sessionId, $data, false, $this->memcachedOptions['expiretime']);
    }

    /**
     * {@inheritdoc}
     */
    public function sessionDestroy($sessionId)
    {
        $this->memcached->delete($sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function sessionGc($lifetime)
    {
        // not required here because memcache will auto expire the records anyhow.
        return true;
    }
}
