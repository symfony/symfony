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

/**
 * The bool argument of the $onHandled callable MUST be passed as a by-reference
 * variable: batch handlers flip it through the reference to signal a late ack,
 * so passing a literal or an unwired local silently breaks batch acking.
 */
interface MessageExecutionStrategyInterface
{
    /**
     * @param callable(Envelope, string, bool &$acked, ?\Throwable): void $onHandled
     */
    public function execute(Envelope $envelope, string $transportName, callable $onHandled): void;

    public function shouldPauseConsumption(): bool;

    /**
     * @param callable(Envelope, string, bool &$acked, ?\Throwable): void $onHandled
     */
    public function wait(callable $onHandled): bool;

    /**
     * @param callable(Envelope, string, bool &$acked, ?\Throwable): void $onHandled
     */
    public function flush(callable $onHandled, bool|float $force = false): bool;

    public function shutdown(): void;
}
