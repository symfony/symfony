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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class FailedMessageProcessingMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // look for "received" messages decorated with the SentToFailureTransportStamp
        /** @var SentToFailureTransportStamp|null $sentToFailureStamp */
        $sentToFailureStamp = $envelope->last(SentToFailureTransportStamp::class);
        if (null !== $sentToFailureStamp && null !== $envelope->last(ReceivedStamp::class)) {
            // mark the message as "received" from the original transport
            // this guarantees the same behavior as when originally received
            $envelope = $envelope->with(new ReceivedStamp($sentToFailureStamp->getOriginalReceiverName()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
