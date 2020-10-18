<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\GooglePubSub\Transport;

use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\Subscription;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 */
class GooglePubSubReceiver implements ReceiverInterface
{
    private const MESSAGE_ATTRIBUTE_NAME = 'X-Symfony-Messenger';

    private $subscription;
    private $serializer;

    /** @var array[] */
    private $buffer = [];

    public function __construct(Subscription $subscription, SerializerInterface $serializer = null)
    {
        $this->subscription = $subscription;
        $this->serializer   = $serializer ?? new PhpSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        $gpsEnvelope = $this->getMessage();
        if (null === $gpsEnvelope) {
            return;
        }

        try {
            $envelope = $this->serializer->decode([
                'body' => $gpsEnvelope['body'],
                'headers' => $gpsEnvelope['headers'],
            ]);
        } catch (MessageDecodingFailedException $exception) {
            $this->subscription->delete($gpsEnvelope['id']);

            throw $exception;
        }

        yield $envelope->with(new GooglePubSubReceivedStamp($gpsEnvelope['id'], $gpsEnvelope['ackId']));
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        $stamp = $this->findReceivedStamp($envelope);

        $this->subscription->acknowledge(new Message([], ['ackId' => $stamp->getAckId()]));
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        $this->ack($envelope);
    }

    private function findReceivedStamp(Envelope $envelope): GooglePubSubReceivedStamp
    {
        /** @var GooglePubSubReceivedStamp|null $stamp */
        $stamp = $envelope->last(GooglePubSubReceivedStamp::class);

        if (null === $stamp) {
            throw new LogicException('No GooglePubSubReceivedStamp found on the Envelope.');
        }

        return $stamp;
    }

    private function getMessage(): ?array
    {
        foreach ($this->getNextMessages() as $message) {
            return $message;
        }

        return null;
    }

    private function getNextMessages(): \Generator
    {
        yield from $this->getPendingMessages();
        yield from $this->getNewMessages();
    }

    private function getPendingMessages(): \Generator
    {
        while (!empty($this->buffer)) {
            yield array_shift($this->buffer);
        }
    }

    private function getNewMessages(): \Generator
    {
        $this->fetchMessages();

        yield from $this->getPendingMessages();
    }

    private function fetchMessages(): void
    {
        foreach ($this->subscription->pull() as $message) {
            $headers = [];

            $attributes = $message->attributes();
            if (isset($attributes[self::MESSAGE_ATTRIBUTE_NAME])) {
                $headers = json_decode($attributes[self::MESSAGE_ATTRIBUTE_NAME], true);
                unset($attributes[self::MESSAGE_ATTRIBUTE_NAME]);
            }

            $headers += $attributes;

            $this->buffer[] = [
                'id'      => $message->id(),
                'ackId'   => $message->ackId(),
                'body'    => $message->data(),
                'headers' => $headers,
            ];
        }
    }
}
