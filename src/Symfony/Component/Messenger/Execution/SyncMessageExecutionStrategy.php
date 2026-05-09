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
use Symfony\Component\Messenger\Stamp\AckStamp;

final class SyncMessageExecutionStrategy implements MessageExecutionStrategyInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly \Closure $onAcknowledge,
    ) {
    }

    public function execute(Envelope $envelope, string $transportName, callable $onHandled): void
    {
        $acked = false;
        $error = null;

        $ack = function (Envelope $handledEnvelope, ?\Throwable $handledError = null) use (&$envelope, &$acked, &$error, $transportName): void {
            $envelope = $handledEnvelope;
            $acked = true;
            $error = $handledError;
            ($this->onAcknowledge)($transportName, $handledEnvelope, $handledError);
        };

        try {
            $envelope = $this->bus->dispatch($envelope->with(new AckStamp($ack)));
            $onHandled($envelope, $transportName, $acked, $error);
        } catch (\Throwable $e) {
            $onHandled($envelope, $transportName, $acked, $e);
        }
    }

    public function shouldPauseConsumption(): bool
    {
        return false;
    }

    public function wait(callable $onHandled): bool
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
