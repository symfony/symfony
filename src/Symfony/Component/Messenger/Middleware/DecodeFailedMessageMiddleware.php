<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Replays the transport serializer when a message could not be decoded initially.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class DecodeFailedMessageMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ContainerInterface $serializerLocator,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if (!$message instanceof MessageDecodingFailedException) {
            return $stack->next()->handle($envelope, $stack);
        }

        // When retrying from the failure transport, use the original transport name
        // so we can look up the correct serializer; fall back to the current ReceivedStamp.
        $transportName = $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName()
            ?? $envelope->last(ReceivedStamp::class)?->getTransportName()
            ?? throw new LogicException('A ReceivedStamp is required to decode a serialized envelope message.');

        if (!$this->serializerLocator->has($transportName)) {
            throw new LogicException(\sprintf('No serializer is configured for the "%s" transport.', $transportName));
        }

        $serializer = $this->serializerLocator->get($transportName);
        if (!$serializer instanceof SerializerInterface) {
            throw new LogicException(\sprintf('The serializer configured for the "%s" transport must implement "%s".', $transportName, SerializerInterface::class));
        }

        $decodedEnvelope = $serializer->decode($message->encodedEnvelope);

        if ($decodedEnvelope->getMessage() instanceof MessageDecodingFailedException) {
            throw $decodedEnvelope->getMessage();
        }

        $envelope = $decodedEnvelope->with(...array_merge(...array_values($envelope->all())));

        return $stack->next()->handle($envelope, $stack);
    }
}
