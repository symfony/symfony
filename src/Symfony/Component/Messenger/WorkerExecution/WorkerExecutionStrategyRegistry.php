<?php

namespace Symfony\Component\Messenger\WorkerExecution;

use RuntimeException;

final class WorkerExecutionStrategyRegistry
{
    /** @var array<string, class-string<WorkerExecutionStrategyInterface>> */
    private array $strategies = [];

    /**
     * @param class-string<WorkerExecutionStrategyInterface> $strategyClass
     */
    public function registerStrategy(string $strategyClass): void
    {
        $this->strategies[$strategyClass::getAlias()] = $strategyClass;
    }

    public function createStrategy(mixed $strategyAlias, mixed $strategyConfig): WorkerExecutionStrategyInterface
    {
        $strategyClass = $this->strategies[$strategyAlias] ?? null;
        if (!$strategyClass) {
            throw new RuntimeException("Unknown strategy alias: '{$strategyAlias}'");
        }

        return new $strategyClass($strategyConfig);
    }
}
