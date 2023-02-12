<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore;

use Symfony\Component\Semaphore\Exception\InvalidArgumentException;

/**
 * Key is a container for the state of the semaphores in stores.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class Key
{
    private $resource;
    private $limit;
    private $weight;
    private $expiringTime;
    private $state = [];

    public function __construct(string $resource, int $limit, int $weight = 1)
    {
        if (1 > $limit) {
            throw new InvalidArgumentException("The limit ($limit) should be greater than 0.");
        }
        if (1 > $weight) {
            throw new InvalidArgumentException("The weight ($weight) should be greater than 0.");
        }
        if ($weight > $limit) {
            throw new InvalidArgumentException("The weight ($weight) should be lower or equals to the limit ($limit).");
        }
        $this->resource = $resource;
        $this->limit = $limit;
        $this->weight = $weight;
    }

    public function __toString(): string
    {
        return $this->resource;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function hasState(string $stateKey): bool
    {
        return isset($this->state[$stateKey]);
    }

    public function setState(string $stateKey, mixed $state): void
    {
        $this->state[$stateKey] = $state;
    }

    public function removeState(string $stateKey): void
    {
        unset($this->state[$stateKey]);
    }

    public function getState(string $stateKey): mixed
    {
        return $this->state[$stateKey];
    }

    public function resetLifetime(): void
    {
        $this->expiringTime = null;
    }

    public function reduceLifetime(float $ttlInSeconds): void
    {
        $newTime = microtime(true) + $ttlInSeconds;

        if (null === $this->expiringTime || $this->expiringTime > $newTime) {
            $this->expiringTime = $newTime;
        }
    }

    /**
     * @return float|null Remaining lifetime in seconds. Null when the key won't expire.
     */
    public function getRemainingLifetime(): ?float
    {
        return null === $this->expiringTime ? null : $this->expiringTime - microtime(true);
    }

    public function isExpired(): bool
    {
        return null !== $this->expiringTime && $this->expiringTime <= microtime(true);
    }
}
