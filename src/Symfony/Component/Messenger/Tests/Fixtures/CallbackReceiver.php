<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class CallbackReceiver implements ReceiverInterface
{
    private $callable;
    private $acknowledgeCount = 0;
    private $rejectCount = 0;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function receive(callable $handler): void
    {
        $callable = $this->callable;
        $callable($handler);
    }

    public function stop(): void
    {
    }

    public function ack(Envelope $envelope): void
    {
        ++$this->acknowledgeCount;
    }

    public function reject(Envelope $envelope): void
    {
        ++$this->rejectCount;
    }

    public function getAcknowledgeCount(): int
    {
        return $this->acknowledgeCount;
    }

    public function getRejectCount(): int
    {
        return $this->rejectCount;
    }
}
