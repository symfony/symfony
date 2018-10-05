<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class MemoryTransport implements TransportInterface
{
    private $envelopes = array();
    private $bus;
    private $stopped = false;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    /**
     * {@inheritdoc}
     */
    public function receive(callable $handler): void
    {
        if (!$this->stopped) {
            while (\count($this->envelopes) > 0) {
                $handler(array_shift($this->envelopes));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->stopped = true;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        $this->envelopes[] = $envelope;

        return $envelope;
    }

    public function flush(): void
    {
        $this->receive(function (Envelope $envelope) {
            if (null === $envelope) {
                return;
            }

            $this->bus->dispatch($envelope->with(new ReceivedStamp()));
        });
    }
}
