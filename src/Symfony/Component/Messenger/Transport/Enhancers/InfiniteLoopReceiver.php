<?php

namespace Symfony\Component\Messenger\Transport\Enhancers;

use Symfony\Component\Messenger\Transport\ReceiverInterface;

/**
 * @author RJ Garcia <rj@bighead.net>
 */
class InfiniteLoopReceiver implements ReceiverInterface
{
    private $receiver;
    private $shouldStop;

    public function __construct(ReceiverInterface $receiver)
    {
        $this->receiver = $receiver;
        $this->shouldStop = false;
    }

    public function receive(callable $handler): void
    {
        while (!$this->shouldStop) {
            try {
                $this->receiver->receive($handler);
            } catch (\Throwable $t) {}

            if (\function_exists('pcntl_signal_dispatch')) {
                \pcntl_signal_dispatch();
            }

            if ($t ?? null) {
                throw $t;
            }
        }
    }

    public function stop(): void
    {
        $this->shouldStop = true;
        $this->receiver->stop();
    }
}
