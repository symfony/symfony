<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmpSql\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Sender\BatchSenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class AmpSqlSender implements BatchSenderInterface
{
    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $id = $this->connection->send(...$this->getMessage($envelope));

        return $envelope->with(new TransportMessageIdStamp($id));
    }

    public function sendBatch(array $envelopes): array
    {
        $ids = $this->connection->sendBatch(array_map($this->getMessage(...), $envelopes));

        foreach ($ids as $key => $id) {
            $envelopes[$key] = $envelopes[$key]->with(new TransportMessageIdStamp($id));
        }

        return $envelopes;
    }

    /**
     * @return array{string, array<string, string>, int} The arguments of Connection::send()
     */
    private function getMessage(Envelope $envelope): array
    {
        try {
            $encodedMessage = $this->serializer->encode($envelope);
        } catch (\Throwable) {
            throw new TransportException('Could not encode the message for AMPHP SQL.');
        }

        return [$encodedMessage['body'], $encodedMessage['headers'] ?? [], $envelope->last(DelayStamp::class)?->getDelay() ?? 0];
    }
}
