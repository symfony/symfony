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

use MongoDB\Driver\Session;
use Symfony\Component\Messenger\Bridge\MongoDb\Stamp\MongoDbReceivedStamp;
use Symfony\Component\Messenger\Bridge\MongoDb\Stamp\MongoDbSessionStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Sender\BatchSenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Alessandro Lai <alessandro.lai85@gmail.com>
 */
class MongoDbSender implements BatchSenderInterface
{
    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $id = $this->connection->send(...$this->getMessage($envelope));

        return $envelope->with(new TransportMessageIdStamp((string) $id));
    }

    public function sendBatch(array $envelopes): array
    {
        [$ids, $exceptions] = $this->connection->sendBatch(array_map($this->getMessage(...), $envelopes));

        foreach ($ids as $key => $id) {
            $envelopes[$key] = $envelopes[$key]->with(new TransportMessageIdStamp((string) $id));
        }

        if ($exceptions) {
            throw new BatchSendFailedException(array_diff_key($envelopes, $exceptions), $exceptions);
        }

        return $envelopes;
    }

    /**
     * @return array{string, array<string, string>, int, ?Session, ?string} The arguments of Connection::send()
     */
    private function getMessage(Envelope $envelope): array
    {
        $encodedMessage = $this->serializer->encode($envelope);

        $delay = $envelope->last(DelayStamp::class)?->getDelay() ?? 0;
        $session = $envelope->last(MongoDbSessionStamp::class)?->getSession();
        $received = $envelope->last(MongoDbReceivedStamp::class);

        // a retry of a message claimed from another queue goes back to the queue it was received from, not to the queue of this transport
        $queueName = null !== $received && $envelope->last(RedeliveryStamp::class) && !$envelope->last(SentToFailureTransportStamp::class)
            ? $received->getQueueName()
            : null;

        return [$encodedMessage['body'], $encodedMessage['headers'] ?? [], $delay, $session, $queueName];
    }
}
