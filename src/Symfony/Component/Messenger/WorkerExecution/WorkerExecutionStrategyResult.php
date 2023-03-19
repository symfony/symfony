<?php

namespace Symfony\Component\Messenger\WorkerExecution;

final class WorkerExecutionStrategyResult
{
    public readonly bool $wereEnvelopesHandled;

    public function __construct(bool $wereEnvelopesHandled)
    {
        $this->wereEnvelopesHandled = $wereEnvelopesHandled;
    }
}
