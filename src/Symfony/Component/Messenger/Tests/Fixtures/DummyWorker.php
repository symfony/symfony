<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\WorkerInterface;

class DummyWorker implements WorkerInterface
{
    private $isStopped = false;
    private $envelopesToReceive;
    private $envelopesHandled = 0;

    public function __construct(array $envelopesToReceive)
    {
        $this->envelopesToReceive = $envelopesToReceive;
    }

    public function run(array $options = [], callable $onHandledCallback = null): void
    {
        foreach ($this->envelopesToReceive as $envelope) {
            if (true === $this->isStopped) {
                break;
            }

            if ($onHandledCallback) {
                $onHandledCallback($envelope);
                ++$this->envelopesHandled;
            }
        }
    }

    public function stop(): void
    {
        $this->isStopped = true;
    }

    public function isStopped(): bool
    {
        return $this->isStopped;
    }

    public function countEnvelopesHandled()
    {
        return $this->envelopesHandled;
    }
}
