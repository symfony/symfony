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

interface MessageExecutionStrategyInterface
{
    /**
     * @param callable(Envelope, string, bool, ?\Throwable): void $onHandled
     */
    public function execute(Envelope $envelope, string $transportName, callable $onHandled): void;

    public function shouldPauseConsumption(): bool;

    /**
     * @param callable(Envelope, string, bool, ?\Throwable): void $onHandled
     */
    public function wait(callable $onHandled): bool;

    /**
     * @param callable(Envelope, string, bool, ?\Throwable): void $onHandled
     */
    public function flush(callable $onHandled, bool|float $force = false): bool;

    public function shutdown(): void;
}
