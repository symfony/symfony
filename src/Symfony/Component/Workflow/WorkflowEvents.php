<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

use Symfony\Component\Workflow\Event\AnnounceEvent;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\LeaveEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * To learn more about how workflow events work, check the documentation
 * entry at {@link https://symfony.com/doc/current/workflow/usage.html#using-events}.
 */
final class WorkflowEvents
{
    /**
     * @Event("Symfony\Component\Workflow\Event\GuardEvent")
     */
    public const GUARD = 'workflow.guard';

    /**
     * @Event("Symfony\Component\Workflow\Event\LeaveEvent")
     */
    public const LEAVE = 'workflow.leave';

    /**
     * @Event("Symfony\Component\Workflow\Event\TransitionEvent")
     */
    public const TRANSITION = 'workflow.transition';

    /**
     * @Event("Symfony\Component\Workflow\Event\EnterEvent")
     */
    public const ENTER = 'workflow.enter';

    /**
     * @Event("Symfony\Component\Workflow\Event\EnteredEvent")
     */
    public const ENTERED = 'workflow.entered';

    /**
     * @Event("Symfony\Component\Workflow\Event\CompletedEvent")
     */
    public const COMPLETED = 'workflow.completed';

    /**
     * @Event("Symfony\Component\Workflow\Event\AnnounceEvent")
     */
    public const ANNOUNCE = 'workflow.announce';

    /**
     * Event aliases.
     *
     * These aliases can be consumed by RegisterListenersPass.
     */
    public const ALIASES = [
        GuardEvent::class => self::GUARD,
        LeaveEvent::class => self::LEAVE,
        TransitionEvent::class => self::TRANSITION,
        EnterEvent::class => self::ENTER,
        EnteredEvent::class => self::ENTERED,
        CompletedEvent::class => self::COMPLETED,
        AnnounceEvent::class => self::ANNOUNCE,
    ];

    private function __construct()
    {
    }
}
