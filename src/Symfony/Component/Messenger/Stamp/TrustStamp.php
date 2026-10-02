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

use Symfony\Component\Messenger\Envelope;

/**
 * Tells whether the content of an envelope can be trusted, because it was created in this process or its signature was verified on receipt.
 *
 * Only the instances created by trusted() count: one that is unserialized or denormalized from a message does not.
 * A transport that yields messages created in this process adds TrustStamp::trusted() to them.
 */
final class TrustStamp implements NonSendableStampInterface
{
    private static ?\WeakMap $trusted = null;

    private function __construct()
    {
    }

    public static function trusted(): self
    {
        self::$trusted ??= new \WeakMap();
        self::$trusted[$stamp = new self()] = true;

        return $stamp;
    }

    /**
     * Marks an envelope as untrusted when it loses the ReceivedStamp that tells it came from a transport.
     */
    public static function untrusted(): self
    {
        return new self();
    }

    public function isTrusted(): bool
    {
        return isset(self::$trusted[$this]);
    }

    /**
     * Tells whether an envelope can be trusted: its last TrustStamp decides, and without one, an envelope that no transport delivered was dispatched in this process.
     */
    public static function isEnvelopeTrusted(Envelope $envelope): bool
    {
        return ($stamp = $envelope->last(self::class)) ? $stamp->isTrusted() : null === $envelope->last(ReceivedStamp::class);
    }
}
