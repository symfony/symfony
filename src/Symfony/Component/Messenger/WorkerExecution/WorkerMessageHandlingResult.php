<?php

namespace Symfony\Component\Messenger\WorkerExecution;

final class WorkerMessageHandlingResult
{
    public readonly bool $shouldStop;

    public function __construct(bool $shouldStop)
    {
        $this->shouldStop = $shouldStop;
    }
}
