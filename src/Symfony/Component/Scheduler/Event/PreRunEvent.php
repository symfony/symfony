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

class PreRunEvent
{
    private bool $shouldCancel = false;

    public function __construct(
        private readonly ScheduleProviderInterface $schedule,
        private readonly MessageContext $messageContext,
        private readonly object $message,
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

    public function shouldCancel(?bool $shouldCancel = null): bool
    {
        if (null !== $shouldCancel) {
            $this->shouldCancel = $shouldCancel;
        }

        return $this->shouldCancel;
    }
}
