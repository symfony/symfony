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
 * NativeMemcacheSessionStorage.
 *
 * Session based on native PHP memcache database handler.
 *
 * @author Drak <drak@zikula.org>
 */
class NativeMemcacheSessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    private $savePath;

    /**
     * Constructor.
     *
     * @param string                $savePath   Path of memcache server.
     * @param array                 $options    Session configuration options.
     *
     * @see AbstractSessionStorage::__construct()
     */
    public function __construct($savePath = 'tcp://127.0.0.1:11211?persistent=0', array $options = array())
    {
        if (!extension_loaded('memcache')) {
            throw new \RuntimeException('PHP does not have "memcache" session module registered');
        }

        $this->savePath = $savePath;
        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerSaveHandlers()
    {
        ini_set('session.save_handler', 'memcache');
        ini_set('session.save_path', $this->savePath);
    }

    /**
     * {@inheritdoc}
     *
     * Sets any values memcached ini values.
     *
     * @see http://www.php.net/manual/en/memcache.ini.php
     */
    protected function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            if (in_array($key, array(
                'memcache.allow_failover', 'memcache.max_failover_attempts',
                'memcache.chunk_size', 'memcache.default_port', 'memcache.hash_strategy',
                'memcache.hash_function', 'memcache.protocol', 'memcache.redundancy',
                'memcache.session_redundancy', 'memcache.compress_threshold',
                'memcache.lock_timeout'))) {
                ini_set($key, $value);
            }
        }

        parent::setOptions($options);
    }
}
