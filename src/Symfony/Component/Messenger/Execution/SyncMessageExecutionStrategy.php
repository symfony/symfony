<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Execution;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SyncMessageExecutionStrategy implements MessageExecutionStrategyInterface
{
    private AckAwareDispatcher $dispatcher;

    public function __construct(
        MessageBusInterface $bus,
        private readonly \Closure $onAcknowledge,
    ) {
        $this->dispatcher = new AckAwareDispatcher($bus);
    }

    public function execute(Envelope $envelope, string $transportName, callable $onHandled): void
    {
        $onAcknowledge = $onHandled instanceof MessageExecutionCallback ? $onHandled->acknowledge(...) : $this->onAcknowledge;

        $acked = false;
        $result = $this->dispatcher->dispatch($envelope, $transportName, $onAcknowledge, $acked);

        $onHandled($result->envelope, $transportName, $acked, $result->error);
    }

    public function shouldPauseConsumption(): bool
    {
        return false;
    }

    public function wait(callable $onHandled, ?float $timeout = null): bool
    {
        return false;
    }

    public function flush(callable $onHandled, bool|float $force = false): bool
    {
        return false;
    }

    public function shutdown(): void
    {
    }
}
