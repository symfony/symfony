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
use Symfony\Component\Semaphore\Exception\UnserializableKeyException;

/**
 * Key is a container for the state of the semaphores in stores.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class Key
{
    private ?float $expiringTime = null;
    private array $state = [];
    private bool $serializable = true;
    private ?string $unserializableOwner = null;

    public function __construct(
        private string $resource,
        private int $limit,
        private int $weight = 1,
    ) {
        if (1 > $limit) {
            throw new InvalidArgumentException("The limit ($limit) should be greater than 0.");
        }
        if (1 > $weight) {
            throw new InvalidArgumentException("The weight ($weight) should be greater than 0.");
        }
        if ($weight > $limit) {
            throw new InvalidArgumentException("The weight ($weight) should be lower or equals to the limit ($limit).");
        }
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

    /**
     * Marks the key as unserializable for the remainder of its in-process lifetime.
     *
     * This is a one-way latch: once flipped, {@see __serialize()} will throw
     * {@see UnserializableKeyException} even after the slot has been released
     * via {@see PersistingStoreInterface::delete()}. Stores that attach
     * non-portable per-process state to the key (typically as
     * {@see setState()} values that cannot be reconstructed from a payload,
     * such as live {@see \Symfony\Component\Lock\LockInterface} instances)
     * are expected to call this after a successful acquisition so the key
     * cannot accidentally leave the owning process.
     *
     * @param string|null $owner Class name of the store that owns the
     *                           non-portable state, surfaced in the
     *                           {@see UnserializableKeyException} message
     */
    public function markUnserializable(?string $owner = null): void
    {
        $this->serializable = false;
        $this->unserializableOwner = $owner;
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

    public function __unserialize(array $data): void
    {
        if (($data['resource'] ?? $data["\0".self::class."\0resource"] ?? null) instanceof \Stringable) {
            throw new \BadMethodCallException('Cannot unserialize '.self::class);
        }

        $this->__construct(
            $data['resource'] ?? $data["\0".self::class."\0resource"],
            $data['limit'] ?? $data["\0".self::class."\0limit"],
            $data['weight'] ?? $data["\0".self::class."\0weight"],
        );

        $this->expiringTime = $data['expiringTime'] ?? $data["\0".self::class."\0expiringTime"] ?? null;
        $this->state = $data['state'] ?? $data["\0".self::class."\0state"] ?? [];
    }

    public function __serialize(): array
    {
        if (!$this->serializable) {
            $owner = $this->unserializableOwner;
            throw new UnserializableKeyException(null === $owner ? 'The key cannot be serialized.' : \sprintf('The key cannot be serialized: state owned by "%s" is not portable.', $owner));
        }

        return [
            'resource' => $this->resource,
            'limit' => $this->limit,
            'weight' => $this->weight,
            'expiringTime' => $this->expiringTime,
            'state' => $this->state,
        ];
    }
}
