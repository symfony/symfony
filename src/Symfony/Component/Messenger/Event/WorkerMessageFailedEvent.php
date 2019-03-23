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

use Symfony\Component\Messenger\Envelope;

/**
 * Dispatched when a message was received from a transport and handling failed.
 *
 * The event name is the class name.
 *
 * @experimental in 4.3
 */
class WorkerMessageFailedEvent extends AbstractWorkerMessageEvent
{
    private $throwable;
    private $willRetry;

    public function __construct(Envelope $envelope, string $receiverName, \Throwable $error, bool $willRetry)
    {
        $this->throwable = $error;
        $this->willRetry = $willRetry;

        parent::__construct($envelope, $receiverName);
    }

    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }

    public function willRetry(): bool
    {
        return $this->willRetry;
    }
}
