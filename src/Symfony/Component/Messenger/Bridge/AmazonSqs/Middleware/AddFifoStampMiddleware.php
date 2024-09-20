<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Middleware;

use Symfony\Component\Messenger\Bridge\AmazonSqs\MessageDeduplicationAwareInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\MessageGroupAwareInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFifoStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class AddFifoStampMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $messageGroupId = null;
        $messageDeduplicationId = null;

        if ($message instanceof MessageGroupAwareInterface) {
            $messageGroupId = $message->getMessageGroupId();
        }
        if ($message instanceof MessageDeduplicationAwareInterface) {
            $messageDeduplicationId = $message->getMessageDeduplicationId();
        }

        if (null !== $messageGroupId || null !== $messageDeduplicationId) {
            $envelope = $envelope->with(new AmazonSqsFifoStamp($messageGroupId, $messageDeduplicationId));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
