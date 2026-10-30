<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;

/**
 * Marker stamp for messages that can be ack/nack'ed.
 */
final class AckStamp implements NonSendableStampInterface
{
    /**
     * @param \Closure(Envelope, \Throwable|null):void $ack
     * @param \Closure(HandlerDescriptor):void|null    $deferFlush Asks to flush the batch handler in a dispatch of its own, right after the dispatch of the message
     */
    public function __construct(
        private readonly \Closure $ack,
        private readonly ?\Closure $deferFlush = null,
    ) {
    }

    public function ack(Envelope $envelope, ?\Throwable $e = null): void
    {
        ($this->ack)($envelope, $e);
    }

    /**
     * Asks to flush the batch handler in a dispatch of its own, right after the dispatch of the message.
     *
     * @return bool Whether the batch handler will be flushed, false when it should flush the batch now
     */
    public function deferFlush(HandlerDescriptor $handlerDescriptor): bool
    {
        if (null === $this->deferFlush) {
            return false;
        }

        ($this->deferFlush)($handlerDescriptor);

        return true;
    }
}
