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
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class RedisSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @var integer
     */
    private $lifetime;

    /**
     * Constructor
     *
     * @param \Redis $redis     The redis instance
     * @param integer $lifetime Max lifetime in seconds to keep sessions stored.
     */
    public function __construct(\Redis $redis, $lifetime)
    {
        $this->redis = $redis;
        $this->lifetime = $lifetime;
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
    public function read($sessionId)
    {
        return (string) $this->redis->get($sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function write($sessionId, $data)
    {
        return $this->redis->setex($sessionId, $this->lifetime, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($sessionId)
    {
        return 1 === $this->redis->delete($sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function gc($lifetime)
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
}