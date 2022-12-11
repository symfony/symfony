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
 * Native session handler using php-memcached, PHP's Memcache extension (ext-memcached).
 *
 * @author Maurits van der Schee <maurits@vdschee.nl>
 */
class NativeMemcachedSessionHandler extends \SessionHandler
{
    /**
     * @param string $savePath tells php-memcached where to store the sessions
     *
     * @see https://github.com/php-memcached-dev/php-memcached for further details.
     */
    public function __construct(string $savePath = null, array $sessionOptions = null)
    {
        // 
        // Sessions support (from: https://www.php.net/manual/en/memcached.sessions.php)
        //
        // Memcached provides a custom session handler that can be used to store user sessions in memcache. 
        // A completely separate memcached instance is used for that internally, so you can use a different 
        // server pool if necessary. The session keys are stored under the prefix memc.sess.key., so be aware 
        // of this if you use the same server pool for sessions and generic caching.
        //
        // - session.save_handler string
        //   Set to memcached to enable sessions support.
        //
        // - session.save_path string
        //   Defines a comma separated of hostname:port entries to use for session server 
        //   pool, for example "sess1:11211, sess2:11211".
        //

        $savePath ??= ini_get('session.save_path');

        ini_set('session.save_path', $savePath);
        ini_set('session.save_handler', 'memcached');

        //
        // Runtime Configuration (from: https://www.php.net/manual/en/memcached.configuration.php)
        // 
        // Here's a short explanation of the configuration directives.
        // 
        // - memcached.sess_locking bool
        //   Use session locking. Valid values: On, Off, the default is On.
        // 
        // - memcached.sess_prefix string
        //   Memcached session key prefix. Valid values are strings less than 219 bytes long. 
        //   The default value is "memc.sess.key."
        // 
        // - memcached.sess_lock_expire int
        //   The time, in seconds, before a lock should release itself. Setting to 0 results in the 
        //   default behaviour, which is to use PHP's max_execution_time. Default is 0.
        // 
        // - memcached.sess_lock_retries int
        //   The number of times to retry locking the session lock, not including the first attempt. 
        //   Default is 5.
        // 
        // - memcached.sess_lock_wait_min int
        //   The minimum time, in milliseconds, to wait between session lock attempts. This value is 
        //   double on each lock retry until memcached.sess_lock_wait_max is reached, after which any 
        //   further retries will take sess_lock_wait_max seconds. The default is 150.
        //

        $sessionName = $sessionOptions['name'] ?? ini_get('session.name');

        $prefix = "memc.sess.key.$sessionName.";
        $lock_expire = ini_get("max_execution_time") ?: 30; // 30s
        $lock_wait_time = ini_get('memcached.sess_lock_wait_min') ?: 150; // 150ms
        $lock_retries = (int) ($lock_expire / ($lock_wait_time / 1000)); // 200x

        ini_set('memcached.sess_locking', 1);
        ini_set('memcached.sess_prefix', $prefix);
        ini_set('memcached.sess_lock_expire', $lock_expire);
        ini_set('memcached.sess_lock_wait_min', $lock_wait_time);
        ini_set('memcached.sess_lock_retries', $lock_retries);
    }
}
