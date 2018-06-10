<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Asynchronous\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\ReceiverInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class WrapIntoReceivedMessage implements ReceiverInterface
{
    private $decoratedReceiver;

    public function __construct(ReceiverInterface $decoratedConsumer)
    {
        $this->decoratedReceiver = $decoratedConsumer;
    }

    public function receive(callable $handler): void
    {
        $this->decoratedReceiver->receive(function (?Envelope $envelope) use ($handler) {
            if (null !== $envelope) {
                $envelope = $envelope->with(new ReceivedMessage());
            }

            $handler($envelope);
        });
    }

    public function stop(): void
    {
        $this->decoratedReceiver->stop();
    }
}
