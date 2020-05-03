<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Transport;

use AsyncAws\Core\Exception\Http\HttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class AmazonSqsSender implements SenderInterface
{
    private $connection;
    private $serializer;

    public function __construct(Connection $connection, SerializerInterface $serializer)
    {
        $this->connection = $connection;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);

        /** @var DelayStamp|null $delayStamp */
        $delayStamp = $envelope->last(DelayStamp::class);
        $delay = null !== $delayStamp ? (int) ceil($delayStamp->getDelay() / 1000) : 0;

        $messageGroupId = null;
        $messageDeduplicationId = null;

        /** @var AmazonSqsFifoStamp|null $amazonSqsFifoStamp */
        $amazonSqsFifoStamp = $envelope->last(AmazonSqsFifoStamp::class);
        if (null !== $amazonSqsFifoStamp) {
            $messageGroupId = $amazonSqsFifoStamp->getMessageGroupId();
            $messageDeduplicationId = $amazonSqsFifoStamp->getMessageDeduplicationId();
        }

        try {
            $this->connection->send(
                $encodedMessage['body'],
                $encodedMessage['headers'] ?? [],
                $delay,
                $messageGroupId,
                $messageDeduplicationId
            );
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return $envelope;
    }
}
