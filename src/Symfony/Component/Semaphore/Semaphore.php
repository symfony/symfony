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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Semaphore\Exception\InvalidArgumentException;
use Symfony\Component\Semaphore\Exception\RuntimeException;
use Symfony\Component\Semaphore\Exception\SemaphoreAcquiringException;
use Symfony\Component\Semaphore\Exception\SemaphoreExpiredException;
use Symfony\Component\Semaphore\Exception\SemaphoreReleasingException;

/**
 * Semaphore is the default implementation of the SemaphoreInterface.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class Semaphore implements SemaphoreInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $store;
    private $key;
    private $ttlInSecond;
    private $autoRelease;
    private $dirty = false;

    public function __construct(Key $key, PersistingStoreInterface $store, float $ttlInSecond = 300.0, bool $autoRelease = true)
    {
        $this->store = $store;
        $this->key = $key;
        $this->ttlInSecond = $ttlInSecond;
        $this->autoRelease = $autoRelease;

        $this->logger = new NullLogger();
    }

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    /**
     * Automatically releases the underlying semaphore when the object is destructed.
     */
    public function __destruct()
    {
        if (!$this->autoRelease || !$this->dirty || !$this->isAcquired()) {
            return;
        }

        $this->release();
    }

    public function acquire(): bool
    {
        try {
            $this->key->resetLifetime();
            $this->store->save($this->key, $this->ttlInSecond);
            $this->key->reduceLifetime($this->ttlInSecond);
            $this->dirty = true;

            $this->logger->debug('Successfully acquired the "{resource}" semaphore.', ['resource' => $this->key]);

            return true;
        } catch (SemaphoreAcquiringException) {
            $this->logger->notice('Failed to acquire the "{resource}" semaphore. Someone else already acquired the semaphore.', ['resource' => $this->key]);

            return false;
        } catch (\Exception $e) {
            $this->logger->notice('Failed to acquire the "{resource}" semaphore.', ['resource' => $this->key, 'exception' => $e]);

            throw new RuntimeException(sprintf('Failed to acquire the "%s" semaphore.', $this->key), 0, $e);
        }
    }

    public function refresh(float $ttlInSecond = null): void
    {
        if (!$ttlInSecond ??= $this->ttlInSecond) {
            throw new InvalidArgumentException('You have to define an expiration duration.');
        }

        try {
            $this->key->resetLifetime();
            $this->store->putOffExpiration($this->key, $ttlInSecond);
            $this->key->reduceLifetime($ttlInSecond);

            $this->dirty = true;

            $this->logger->debug('Expiration defined for "{resource}" semaphore for "{ttlInSecond}" seconds.', ['resource' => $this->key, 'ttlInSecond' => $ttlInSecond]);
        } catch (SemaphoreExpiredException $e) {
            $this->dirty = false;
            $this->logger->notice('Failed to define an expiration for the "{resource}" semaphore, the semaphore has expired.', ['resource' => $this->key]);

            throw $e;
        } catch (\Exception $e) {
            $this->logger->notice('Failed to define an expiration for the "{resource}" semaphore.', ['resource' => $this->key, 'exception' => $e]);

            throw new RuntimeException(sprintf('Failed to define an expiration for the "%s" semaphore.', $this->key), 0, $e);
        }
    }

    public function isAcquired(): bool
    {
        return $this->dirty = $this->store->exists($this->key);
    }

    public function release(): void
    {
        try {
            $this->store->delete($this->key);
            $this->dirty = false;
        } catch (SemaphoreReleasingException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->notice('Failed to release the "{resource}" semaphore.', ['resource' => $this->key]);

            throw new RuntimeException(sprintf('Failed to release the "%s" semaphore.', $this->key), 0, $e);
        }
    }

    public function isExpired(): bool
    {
        return $this->key->isExpired();
    }

    public function getRemainingLifetime(): ?float
    {
        return $this->key->getRemainingLifetime();
    }
}
