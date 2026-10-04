<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Transport;

use Symfony\Component\Messenger\Bridge\MongoDb\Stamp\MongoDbReceivedStamp;
use Symfony\Component\Messenger\Bridge\MongoDb\Stamp\MongoDbSessionStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Alessandro Lai <alessandro.lai85@gmail.com>
 */
class MongoDbSender implements SenderInterface
{
    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);

        $delay = $envelope->last(DelayStamp::class)?->getDelay() ?? 0;
        $session = $envelope->last(MongoDbSessionStamp::class)?->getSession();
        $received = $envelope->last(MongoDbReceivedStamp::class);

        // a retry of a message claimed from another queue goes back to the queue it was received from, not to the queue of this transport
        $queueName = null !== $received && $envelope->last(RedeliveryStamp::class) && !$envelope->last(SentToFailureTransportStamp::class)
            ? $received->getQueueName()
            : null;

        $id = $this->connection->send($encodedMessage['body'], $encodedMessage['headers'] ?? [], $delay, $session, $queueName);

        return $envelope->with(new TransportMessageIdStamp((string) $id));
    }
}
