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
 * NativeMemcacheSessionHandler.
 *
 * Driver for the memcache session save handler provided by the memcache PHP extension.
 *
 * @see http://php.net/memcache
 *
 * @author Drak <drak@zikula.org>
 */
class NativeMemcacheSessionHandler extends NativeSessionHandler
{
    /**
     * Constructor.
     *
     * @param string $savePath Path of memcache server.
     * @param array  $options  Memcache ini values
     *
     * @throws \RuntimeException If memcache is not available
     */
    public function __construct($savePath = 'tcp://127.0.0.1:11211?persistent=0', array $options = array())
    {
        if (!extension_loaded('memcache')) {
            throw new \RuntimeException('PHP does not have "memcache" session module registered');
        }

        if (null === $savePath) {
            $savePath = ini_get('session.save_path');
        }

        ini_set('session.save_handler', 'memcache');
        ini_set('session.save_path', $savePath);

        $this->setOptions($options);
    }

    /**
     * Set any memcache ini values.
     *
     * @param array  $options  Memcache ini values
     *
     * @see http://php.net/memcache.ini
     */
    protected function setOptions(array $options)
    {
        if (isset($options['memcache.session_redundancy'])) {
            ini_set('memcache.session_redundancy', $options['memcache.session_redundancy']);
        }
    }
}
