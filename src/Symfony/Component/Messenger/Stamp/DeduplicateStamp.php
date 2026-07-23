<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use Symfony\Component\Lock\Key;
use Symfony\Component\Messenger\Exception\LogicException;

/**
 * Carries a deduplication key alongside an Envelope.
 *
 * Security note: the key carried by this stamp is not authenticated. It travels
 * with the envelope through the transport, so any actor able to write into the
 * queue can craft an envelope whose DeduplicateStamp references the
 * deduplication key of another in-flight message and, by failing that crafted
 * message terminally, release the legitimate message's lock (see
 * {@see \Symfony\Component\Messenger\EventListener\ReleaseDeduplicationLockOnFailureListener}).
 *
 * Treat deduplication keys as a best-effort idempotency primitive. They are not
 * a correctness primitive against a hostile queue producer.
 */
final class DeduplicateStamp implements StampInterface
{
    private Key $key;

    public function __construct(
        string|Key $key,
        private ?float $ttl = 300.0,
        private bool $onlyDeduplicateInQueue = false,
    ) {
        if (!class_exists(Key::class)) {
            throw new LogicException(\sprintf('You cannot use the "%s" as the Lock component is not installed. Try running "composer require symfony/lock".', self::class));
        }

        $this->key = \is_string($key) ? new Key($key) : $key;
    }

    public function onlyDeduplicateInQueue(): bool
    {
        return $this->onlyDeduplicateInQueue;
    }

    public function getKey(): Key
    {
        return $this->key;
    }

    public function getTtl(): ?float
    {
        return $this->ttl;
    }
}
