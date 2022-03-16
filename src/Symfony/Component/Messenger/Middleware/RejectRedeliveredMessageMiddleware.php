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

use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;

/**
 * Middleware that throws a RejectRedeliveredMessageException when a message is detected that has been redelivered by AMQP.
 *
 * The middleware runs before the HandleMessageMiddleware and prevents redelivered messages from being handled directly.
 * The thrown exception is caught by the worker and will trigger the retry logic according to the retry strategy.
 *
 * AMQP redelivers messages when they do not get acknowledged or rejected. This can happen when the connection times out
 * or an exception is thrown before acknowledging or rejecting. When such errors happen again while handling the
 * redelivered message, the message would get redelivered again and again. The purpose of this middleware is to prevent
 * infinite redelivery loops and to unblock the queue by republishing the redelivered messages as retries with a retry
 * limit and potential delay.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
class RejectRedeliveredMessageMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $amqpReceivedStamp = $envelope->last(AmqpReceivedStamp::class);
        if ($amqpReceivedStamp instanceof AmqpReceivedStamp && $amqpReceivedStamp->getAmqpEnvelope()->isRedelivery()) {
            throw new RejectRedeliveredMessageException('Redelivered message from AMQP detected that will be rejected and trigger the retry logic.');
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
