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
    private $expiringDate;
    private $state = array();

    /**
     * @param string $resource
     */
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

    /**
     * @param float $ttl The expiration delay of locks in seconds.
     */
    public function reduceLifetime($ttl)
    {
        $newExpiringDate = \DateTimeImmutable::createFromFormat('U.u', (string) (microtime(true) + $ttl));

        if (null === $this->expiringDate || $newExpiringDate < $this->expiringDate) {
            $this->expiringDate = $newExpiringDate;
        }
    }

    public function resetExpiringDate()
    {
        $this->expiringDate = null;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getExpiringDate()
    {
        return $this->expiringDate;
    }
}
