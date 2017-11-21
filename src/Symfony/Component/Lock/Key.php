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
    private $state = array();

    public function __construct(string $resource)
    {
        $this->resource = $resource;
    }

    public function __toString()
    {
        return $this->resource;
    }

    public function hasState(string $stateKey): bool
    {
        return isset($this->state[$stateKey]);
    }

    public function setState(string $stateKey, $state): void
    {
        $this->state[$stateKey] = $state;
    }

    public function removeState(string $stateKey): void
    {
        unset($this->state[$stateKey]);
    }

    public function getState(string $stateKey)
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
