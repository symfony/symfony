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
 * NativeMemcachedSessionHandler.
 *
 * Driver for the memcached session save handler provided by the memcached PHP extension.
 *
 * @see http://php.net/memcached.sessions
 *
 * @author Drak <drak@zikula.org>
 */
class NativeMemcachedSessionHandler extends NativeSessionHandler
{
    /**
     * Constructor.
     *
     * @param string $savePath Comma separated list of servers: e.g. memcache1.example.com:11211,memcache2.example.com:11211
     * @param array  $options  Memcached ini values
     *
     * @throws \RuntimeException When the memcached extension is not available
     */
    public function __construct($savePath = '127.0.0.1:11211', array $options = array())
    {
        if (!extension_loaded('memcached')) {
            throw new \RuntimeException('PHP does not have "memcached" session module registered');
        }

        if (null === $savePath) {
            $savePath = ini_get('session.save_path');
        }

        ini_set('session.save_handler', 'memcached');
        ini_set('session.save_path', $savePath);

        $this->setOptions($options);
    }

    /**
     * Set any memcached ini values.
     *
     * @param array  $options  Memcached ini values
     *
     * @see https://github.com/php-memcached-dev/php-memcached/blob/master/memcached.ini
     */
    protected function setOptions(array $options)
    {
        $validOptions = array_flip(array(
            'memcached.sess_locking',
            'memcached.sess_lock_wait',
            'memcached.sess_prefix'
        ));

        foreach ($options as $key => $value) {
            if (isset($validOptions[$key])) {
                ini_set($key, $value);
            }
        }
    }
}
