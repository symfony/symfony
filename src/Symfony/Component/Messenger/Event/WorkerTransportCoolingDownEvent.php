<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Event;

use Symfony\Component\Messenger\Cooldown\CooldownStatus;

/**
 * Dispatched when the worker starts skipping a transport because its cooldown strategy says it is cooling down.
 *
 * It is dispatched once each time the worker starts skipping the transport, not for each fetch it skips, so a cooldown
 * that ends before the worker gets back to the transport is not reported.
 */
final class WorkerTransportCoolingDownEvent
{
    public function __construct(
        private string $transportName,
        private CooldownStatus $status,
    ) {
    }

    public function getTransportName(): string
    {
        return $this->transportName;
    }

    public function getStatus(): CooldownStatus
    {
        return $this->status;
    }
}
