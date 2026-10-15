<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Cooldown;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Decides when a worker stops consuming a transport for a while, after messages received from it failed or fetching from it failed.
 *
 * The failed messages are retried or sent to the failure transport as usual. The cooldown keeps the worker
 * from handling the next messages while the cause of the failures would make them fail too.
 */
interface CooldownStrategyInterface
{
    /**
     * Checks whether the transport is cooling down.
     *
     * The worker calls it once, right before each fetch from the transport, and skips the transport while it is cooling down.
     * This is where the strategy decides, so it may change its state, to grant a trial call for example. As a fetch can find
     * no message, no recordSuccess() or recordFailure() call is guaranteed to follow.
     */
    public function checkStatus(string $transportName): CooldownStatus;

    public function recordSuccess(string $transportName, Envelope $envelope): void;

    /**
     * @param Envelope|null $envelope The failed message, with the stamps added by the listeners of {@see WorkerMessageFailedEvent}, or null when fetching messages from the transport failed
     */
    public function recordFailure(string $transportName, ?Envelope $envelope, \Throwable $throwable): void;
}
