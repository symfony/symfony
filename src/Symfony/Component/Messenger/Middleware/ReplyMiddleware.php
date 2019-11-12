<?php

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReplyStamp;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpReceivedStamp;

/**
 * Middleware responsible for replying results returned by handler.
 */
class ReplyMiddleware implements MiddlewareInterface
{
    /**
     * @param Envelope $envelope
     * @param StackInterface $stack
     * @return Envelope
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (($handledStamp = $envelope->last(HandledStamp::class))
            && ($replyStamp = $envelope->last(ReplyStamp::class))
        ) {
            $response = $handledStamp->getResult();

            // Make the result available to sender if sync transport is used
            $replyStamp->setResponse($response);

            if ($amqpRecievedStamp = $envelope->last(AmqpReceivedStamp::class)) {
                $replyTo = $amqpRecievedStamp->getAmqpEnvelope()->getReplyTo();
                $amqpRecievedStamp->getConnection()->reply($response, $replyTo);
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
