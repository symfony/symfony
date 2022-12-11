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
 * Native session handler using PhpRedis, PHP's Redis extension (ext-redis).
 *
 * @author Maurits van der Schee <maurits@vdschee.nl>
 */
class NativeRedisSessionHandler extends \SessionHandler
{
    /**
     * @param string $savePath tells PhpRedis where to store the sessions
     *
     * @see https://github.com/phpredis/phpredis#php-session-handler for further details.
     */
    public function __construct(string $savePath = null, array $sessionOptions = null)
    {
        //
        // PHP Session handler (from: https://github.com/phpredis/phpredis#php-session-handler)
        //
        // phpredis can be used to store PHP sessions. To do this, configure session.save_handler and session.save_path
        // in your php.ini to tell phpredis where to store the sessions:
        //
        // session.save_handler = redis
        // session.save_path = "tcp://host1:6379?weight=1, tcp://host2:6379?weight=2&timeout=2.5, tcp://host3:6379?weight=2&read_timeout=2.5"
        //
        // session.save_path can have a simple host:port format too, but you need to provide the tcp:// scheme if
        // you want to use the parameters. The following parameters are available:
        //
        // - weight (integer): the weight of a host is used in comparison with the others in order to
        //   customize the session distribution on several hosts. If host A has twice the weight of host B,
        //   it will get twice the amount of sessions. In the example, host1 stores 20% of all the
        //   sessions (1/(1+2+2)) while host2 and host3 each store 40% (2/(1+2+2)). The target host is
        //   determined once and for all at the start of the session, and doesn't change. The default weight is 1.
        // - timeout (float): the connection timeout to a redis host, expressed in seconds.
        //   If the host is unreachable in that amount of time, the session storage will be unavailable for the client.
        //   The default timeout is very high (86400 seconds).
        // - persistent (integer, should be 1 or 0): defines if a persistent connection should be used.
        // - prefix (string, defaults to "PHPREDIS_SESSION:"): used as a prefix to the Redis key in which the session is stored.
        //   The key is composed of the prefix followed by the session ID.
        // - auth (string, or an array with one or two elements): used to authenticate with the server prior to sending commands.
        // - database (integer): selects a different database.
        //
        // Sessions have a lifetime expressed in seconds and stored in the INI variable "session.gc_maxlifetime".
        // You can change it with ini_set(). The session handler requires a version of Redis supporting EX and NX
        // options of SET command (at least 2.6.12). phpredis can also connect to a unix domain socket:
        // session.save_path = "unix:///var/run/redis/redis.sock?persistent=1&weight=1&database=0"
        //

        $savePath ??= \ini_get('session.save_path');
        $sessionName = $sessionOptions['name'] ?? \ini_get('session.name');

        $savePathParts = explode('?', $savePath, 2);
        parse_str($savePathParts[1] ?? '', $arguments);
        if (!isset($arguments['prefix'])) {
            $arguments['prefix'] = "PHPREDIS_SESSION.$sessionName.";
        }
        $savePathParts[1] = http_build_query($arguments);

        ini_set('session.save_path', implode('?', $savePathParts));
        ini_set('session.save_handler', 'redis');

        //
        // Session locking (from: https://github.com/phpredis/phpredis#session-locking)
        //
        // Support: Locking feature is currently only supported for Redis setup with single master instance
        // (e.g. classic master/slave Sentinel environment).
        // So locking may not work properly in RedisArray or RedisCluster environments.
        //
        // Following INI variables can be used to configure session locking:
        //
        // ; Should the locking be enabled? Defaults to: 0.
        // redis.session.locking_enabled = 1
        // ; How long should the lock live (in seconds)? Defaults to: value of max_execution_time (defaults to 30).
        // redis.session.lock_expire = 60
        // ; How long to wait between attempts to acquire lock, in microseconds (µs)?. Defaults to: 20000
        // redis.session.lock_wait_time = 100000
        // ; Maximum number of times to retry (-1 means infinite). Defaults to: 100
        // redis.session.lock_retries = 300
        //

        $lock_expire = (\ini_get('redis.session.lock_expire') ?: \ini_get('max_execution_time')) ?: 30; // 30s
        $lock_wait_time = (\ini_get('redis.session.lock_wait_time') ?: 20000); // 20ms
        $lock_retries = (int) ($lock_expire / ($lock_wait_time / 1000000)); // 1500x

        ini_set('redis.session.locking_enabled', 1);
        ini_set('redis.session.lock_expire', $lock_expire);
        ini_set('redis.session.lock_wait_time', $lock_wait_time);
        ini_set('redis.session.lock_retries', $lock_retries);
    }
}
