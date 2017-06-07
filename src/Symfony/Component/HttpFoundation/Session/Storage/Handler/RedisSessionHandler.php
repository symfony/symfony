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
 * RedisSessionHandler
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author stackoverflow <admin@2.pl>
 *
 */
class RedisSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var \Redis  driver
     */
    private $redis;

    /**
     * @var int Time to live in seconds
     */
    private $ttl;

    /**
     * Class Constructor
     *
     * @param \Redis $redis A memcached instance
     * @param int   $ttl    Session lifetime
     */
    public function __construct(\Redis $redis, $ttl)
    {
        $this->redis = $redis;
        $this->ttl = $ttl;
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        return (string) $this->redis->get($sessionId);
    }
    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        return $this->redis->setex($sessionId, $this->ttl, $data);
    }
    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        return 1 === $this->redis->delete($sessionId);
    }
    /**
     * {@inheritdoc}
     */
    public function gc($lifetime)
    {
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }
}

