<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sync;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Transport that immediately marks messages as received and dispatches for handling.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class SyncTransport implements TransportInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function get(): iterable
    {
        throw new InvalidArgumentException('You cannot receive messages from the Messenger SyncTransport.');
    }

    public function ack(Envelope $envelope): void
    {
        throw new InvalidArgumentException('You cannot call ack() on the Messenger SyncTransport.');
    }

    public function reject(Envelope $envelope): void
    {
        throw new InvalidArgumentException('You cannot call reject() on the Messenger SyncTransport.');
    }

    public function send(Envelope $envelope): Envelope
    {
        /** @var SentStamp|null $sentStamp */
        $sentStamp = $envelope->last(SentStamp::class);
        $alias = null === $sentStamp ? 'sync' : ($sentStamp->getSenderAlias() ?: $sentStamp->getSenderClass());

        $envelope = $envelope->with(new ReceivedStamp($alias));

        return $this->messageBus->dispatch($envelope);
    }
}
