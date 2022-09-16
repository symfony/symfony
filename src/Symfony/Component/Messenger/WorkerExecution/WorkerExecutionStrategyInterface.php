<?php

namespace Symfony\Component\Messenger\WorkerExecution;

interface WorkerExecutionStrategyInterface
{
    public function __construct(array $options);

    /**
     * Get a unique alias under which the strategy will be registered in the registry.
     *
     * @see WorkerExecutionStrategyRegistry::registerStrategy()
     */
    public static function getAlias(): string;

    public function processQueueTasks(WorkerExecutionStrategyContext $context): WorkerExecutionStrategyResult;
}
