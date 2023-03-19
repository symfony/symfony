<?php

namespace Symfony\Component\Messenger\WorkerExecution;

final class DefaultWorkerExecutionStrategy implements WorkerExecutionStrategyInterface
{
    public function __construct(array $options)
    {
    }

    public static function getAlias(): string
    {
        return 'default';
    }

    public function processQueueTasks(WorkerExecutionStrategyContext $context): WorkerExecutionStrategyResult
    {
        $envelopeHandled = false;

        foreach ($context->getReceivers() as $transportName => $receiver) {
            if ($context->getQueueNames()) {
                $envelopes = $receiver->getFromQueues($context->getQueueNames());
            } else {
                $envelopes = $receiver->get();
            }

            foreach ($envelopes as $envelope) {
                $envelopeHandled = true;

                $result = $context->handleMessage($envelope, $transportName);

                if ($result->shouldStop) {
                    break 2;
                }
            }

            // after handling a single receiver, quit and start the loop again
            // this should prevent multiple lower priority receivers from
            // blocking too long before the higher priority are checked
            if ($envelopeHandled) {
                break;
            }
        }

        return new WorkerExecutionStrategyResult($envelopeHandled);
    }
}
