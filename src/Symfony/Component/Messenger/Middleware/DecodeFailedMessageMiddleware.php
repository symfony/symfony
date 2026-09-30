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
use Symfony\Component\Messenger\Exception\InvalidMessageSignatureException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\UnverifiedDecodingFailureStamp;
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

        if (($failure = $decodedEnvelope->getMessage()) instanceof MessageDecodingFailedException) {
            // retry listeners look at the thrown exception only: surface an unrecoverable cause so that the message skips retries
            throw $failure->getPrevious() instanceof UnrecoverableExceptionInterface ? $failure->getPrevious() : $failure;
        }

        $received = null !== $envelope->last(ReceivedStamp::class);
        $unverified = $envelope->last(UnverifiedDecodingFailureStamp::class);
        $envelope = $envelope->withoutAll(UnverifiedDecodingFailureStamp::class);

        if ($unverified?->requiresSignature($decodedEnvelope->getMessage())) {
            $busName = $decodedEnvelope->last(BusNameStamp::class)?->getBusName();
            $failureBusName = $envelope->last(BusNameStamp::class)?->getBusName();

            // the bus of the failure was chosen from stamps that nothing verified
            if (null !== $busName && null !== $failureBusName && $busName !== $failureBusName) {
                throw new InvalidMessageSignatureException(\sprintf('The unverified decoding failure of message "%s" is on the "%s" bus, but the message belongs to the "%s" bus.', get_debug_type($decodedEnvelope->getMessage()), $failureBusName, $busName));
            }

            // the stamps an unverified failure was decoded with must not reach a signed message: keep only the ones added since,
            // and the original transport, which already chose the serializer above and routes the message to its handlers
            $envelope = new Envelope($message, array_filter(array_merge(...array_values($envelope->all())), static fn (StampInterface $stamp): bool => $stamp instanceof SentToFailureTransportStamp || !\in_array($stamp, $unverified->stamps, true)));
        }

        // the failed envelope holds the stamps its own decoding kept and the ones added since: they replace the decoded stamps of the same class
        $envelope = new Envelope($decodedEnvelope->getMessage(), array_merge(...array_values($envelope->all() + $decodedEnvelope->all())));

        if (!$received) {
            // a failure redispatched from the failure transport keeps this stamp only to find its serializer
            $envelope = $envelope->withoutAll(SentToFailureTransportStamp::class);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
