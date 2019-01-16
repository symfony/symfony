<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock;

/**
 * Key is a container for the state of the locks in stores.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class Key
{
    private $resource;
    private $expiringTime;
    private $state = [];

    /**
     * @param string $resource
     */
    public function __construct($resource)
    {
        $this->resource = (string) $resource;
    }

    public function __toString()
    {
        return $this->resource;
    }

    /**
     * @param string $stateKey
     *
     * @return bool
     */
    public function hasState($stateKey)
    {
        return isset($this->state[$stateKey]);
    }

    /**
     * @param string $stateKey
     * @param mixed  $state
     */
    public function setState($stateKey, $state)
    {
        $this->state[$stateKey] = $state;
    }

    /**
     * @param string $stateKey
     */
    public function removeState($stateKey)
    {
        unset($this->state[$stateKey]);
    }

    /**
     * @param $stateKey
     *
     * @return mixed
     */
    public function getState($stateKey)
    {
        return $this->state[$stateKey];
    }

    public function resetLifetime()
    {
        $this->expiringTime = null;
    }

    /**
     * @param float $ttl the expiration delay of locks in seconds
     */
    public function reduceLifetime($ttl)
    {
        $newTime = microtime(true) + $ttl;

        if (null === $this->expiringTime || $this->expiringTime > $newTime) {
            $this->expiringTime = $newTime;
        }
    }

    /**
     * Returns the remaining lifetime.
     *
     * @return float|null Remaining lifetime in seconds. Null when the key won't expire.
     */
    public function getRemainingLifetime()
    {
        return null === $this->expiringTime ? null : $this->expiringTime - microtime(true);
    }

    /**
     * @return bool
     */
    public function isExpired()
    {
        return null !== $this->expiringTime && $this->expiringTime <= microtime(true);
    }
}
