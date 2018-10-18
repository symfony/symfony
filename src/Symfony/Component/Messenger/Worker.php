<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\ReceiverInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class Worker
{
    private $receiver;
    private $bus;

    public function __construct(ReceiverInterface $receiver, MessageBusInterface $bus)
    {
        $this->receiver = $receiver;
        $this->bus = $bus;
    }

    /**
     * Receive the messages and dispatch them to the bus.
     */
    public function run()
    {
        if (\function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () {
                $this->receiver->stop();
            });
        }

        $this->receiver->receive(function (?Envelope $envelope) {
            if (null === $envelope) {
                return;
            }

            $this->bus->dispatch($envelope->with(new ReceivedStamp()));
        });
    }
}
