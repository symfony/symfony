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
        $encodedMessage = $this->complyWithAmazonSqsRequirements($encodedMessage);

        /** @var DelayStamp|null $delayStamp */
        $delayStamp = $envelope->last(DelayStamp::class);
        $delay = null !== $delayStamp ? (int) ceil($delayStamp->getDelay() / 1000) : 0;

        $messageGroupId = null;
        $messageDeduplicationId = null;
        $xrayTraceId = null;

        /** @var AmazonSqsFifoStamp|null $amazonSqsFifoStamp */
        $amazonSqsFifoStamp = $envelope->last(AmazonSqsFifoStamp::class);
        if (null !== $amazonSqsFifoStamp) {
            $messageGroupId = $amazonSqsFifoStamp->getMessageGroupId();
            $messageDeduplicationId = $amazonSqsFifoStamp->getMessageDeduplicationId();
        }

        /** @var AmazonSqsXrayTraceHeaderStamp|null $amazonSqsXrayTraceHeaderStamp */
        $amazonSqsXrayTraceHeaderStamp = $envelope->last(AmazonSqsXrayTraceHeaderStamp::class);
        if (null !== $amazonSqsXrayTraceHeaderStamp) {
            $xrayTraceId = $amazonSqsXrayTraceHeaderStamp->getTraceId();
        }

        try {
            $this->connection->send(
                $encodedMessage['body'],
                $encodedMessage['headers'] ?? [],
                $delay,
                $messageGroupId,
                $messageDeduplicationId,
                $xrayTraceId
            );
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return $envelope;
    }

    /**
     * @see https://docs.aws.amazon.com/AWSSimpleQueueService/latest/APIReference/API_SendMessage.html
     *
     * @param array{body: string, headers?: array<string>} $encodedMessage
     *
     * @return array{body: string, headers?: array<string>}
     */
    private function complyWithAmazonSqsRequirements(array $encodedMessage): array
    {
        if (preg_match('/[^\x20-\x{D7FF}\xA\xD\x9\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', $encodedMessage['body'])) {
            $encodedMessage['body'] = base64_encode($encodedMessage['body']);
        }

        return $encodedMessage;
    }
}
