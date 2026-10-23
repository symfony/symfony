<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ExpirationStamp;

/**
 * Discards the messages whose {@see ExpirationStamp} deadline has passed, instead of sending or handling them.
 */
final class ExpirationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ?ClockInterface $clock = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(ExpirationStamp::class);

        if (!$stamp?->isExpired(($this->clock ?? Clock::get())->now())) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Dropping a message without a word is worse than keeping it, so say which one went and when it was due.
        $this->logger?->warning('Discarding message {class} expired on {expiration}', [
            'class' => $envelope->getMessage()::class,
            'expiration' => $stamp->getExpiresAt()->format(\DateTimeInterface::RFC3339_EXTENDED),
        ]);

        return $envelope;
    }
}
