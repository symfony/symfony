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
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class AmpSqlSender implements SenderInterface
{
    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        try {
            $encodedMessage = $this->serializer->encode($envelope);
        } catch (\Throwable) {
            throw new TransportException('Could not encode the message for AMPHP SQL.');
        }

        $delay = $envelope->last(DelayStamp::class)?->getDelay() ?? 0;
        $id = $this->connection->send($encodedMessage['body'], $encodedMessage['headers'] ?? [], $delay);

        return $envelope->with(new TransportMessageIdStamp($id));
    }
}
