<?php

namespace Symfony\Component\Messenger\WorkerExecution;

use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

final class WorkerExecutionStrategyContext
{
    private Worker $worker;

    /** @var string[] */
    private array $queueNames;

    public function __construct(Worker $worker, array $queueNames)
    {
        $this->worker = $worker;
        $this->queueNames = $queueNames;
    }

    /**
     * @return ReceiverInterface[] Where the key is the transport name
     */
    public function getReceivers(): array
    {
        return $this->worker->getReceivers();
    }

    /**
     * @return string[]
     */
    public function getQueueNames(): array
    {
        return $this->queueNames;
    }

    /**
     * The strategy *must* stop executing and return immediately if the result of this function
     * requests so.
     */
    public function handleMessage(mixed $envelope, int|string $transportName): WorkerMessageHandlingResult
    {
        $this->worker->rateLimit($transportName);
        $this->worker->handleMessage($envelope, $transportName);
        $this->worker->getEventDispatcher()?->dispatch(new WorkerRunningEvent($this->worker, false));

        return new WorkerMessageHandlingResult($this->worker->getShouldStop());
    }
}
