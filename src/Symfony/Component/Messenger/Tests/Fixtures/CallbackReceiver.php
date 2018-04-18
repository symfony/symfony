<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Transport\ReceiverInterface;

class CallbackReceiver implements ReceiverInterface
{
    private $callable;

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
}
