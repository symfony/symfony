<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Redis\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Sender\BatchSenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Alexander Schranz <alexander@sulu.io>
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class RedisSender implements BatchSenderInterface
{
    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $id = $this->connection->add(...$this->getMessage($envelope));

        return $envelope->with(new TransportMessageIdStamp($id));
    }

    public function sendBatch(array $envelopes): array
    {
        [$ids, $exceptions] = $this->connection->addBatch(array_map($this->getMessage(...), $envelopes));

        foreach ($ids as $key => $id) {
            $envelopes[$key] = $envelopes[$key]->with(new TransportMessageIdStamp($id));
        }

        if ($exceptions) {
            throw new BatchSendFailedException(array_diff_key($envelopes, $exceptions), $exceptions);
        }

        return $envelopes;
    }

    /**
     * @return array{string, array, int} The arguments of Connection::add()
     */
    private function getMessage(Envelope $envelope): array
    {
        $encodedMessage = $this->serializer->encode($envelope);

        /** @var DelayStamp|null $delayStamp */
        $delayStamp = $envelope->last(DelayStamp::class);
        $delayInMs = null !== $delayStamp ? $delayStamp->getDelay() : 0;

        return [$encodedMessage['body'], $encodedMessage['headers'] ?? [], $delayInMs];
    }
}
