<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * Stamp added by @see AmqpSender when a client wants a response from handler.
 * Client gets a response from a dedicated exclusive queue.
 */
class AmqpReplyStamp implements NonSendableStampInterface
{
    /**
     * @var \AMQPQueue
     */
    private $replyQueue;

    /**
     * AmqpReplyStamp constructor.
     * @param \AMQPQueue $replyQueue
     */
    public function __construct(\AMQPQueue $replyQueue)
    {
        $this->replyQueue = $replyQueue;
    }

    /**
     * @return string
     */
    public function getResponse(): string
    {
        $response = null;
        $this->replyQueue->consume(function(\AMQPEnvelope $envelope) use (&$response) {
            $response = $envelope->getBody();

            return false;
        });

        return $response;
    }
}
