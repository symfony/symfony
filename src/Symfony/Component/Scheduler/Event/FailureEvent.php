<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Event;

use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

class FailureEvent
{
    private bool $shouldIgnore = false;

    public function __construct(
        private readonly ScheduleProviderInterface $schedule,
        private readonly MessageContext $messageContext,
        private readonly object $message,
        private readonly \Throwable $error,
    ) {
    }

    public function getMessageContext(): MessageContext
    {
        return $this->messageContext;
    }

    public function getSchedule(): ScheduleProviderInterface
    {
        return $this->schedule;
    }

    public function getMessage(): object
    {
        return $this->message;
    }

    public function getError(): \Throwable
    {
        return $this->error;
    }

    public function shouldIgnore(?bool $shouldIgnore = null): bool
    {
        if (null !== $shouldIgnore) {
            $this->shouldIgnore = $shouldIgnore;
        }

        return $this->shouldIgnore;
    }
}
