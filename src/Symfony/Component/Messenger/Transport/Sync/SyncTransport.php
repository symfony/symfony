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
use Symfony\Component\Messenger\Stamp\ForceCallHandlersStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * A "fake" transport that marks messages to be handled immediately.
 *
 * @experimental in 4.3
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class SyncTransport implements TransportInterface
{
    public function get(): iterable
    {
        throw new InvalidArgumentException('You cannot receive messages from the SyncTransport.');
    }

    public function stop(): void
    {
        throw new InvalidArgumentException('You cannot call stop() on the SyncTransport.');
    }

    public function ack(Envelope $envelope): void
    {
        throw new InvalidArgumentException('You cannot call ack() on the SyncTransport.');
    }

    public function reject(Envelope $envelope): void
    {
        throw new InvalidArgumentException('You cannot call reject() on the SyncTransport.');
    }

    public function send(Envelope $envelope): Envelope
    {
        return $envelope->with(new ForceCallHandlersStamp());
    }
}
