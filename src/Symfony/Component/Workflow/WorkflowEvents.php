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

/**
 * To learn more about how workflow events work, check the documentation
 * entry at {@link https://symfony.com/doc/current/workflow/usage.html#using-events}.
 */
final class WorkflowEvents
{
    /**
     * @Event("Symfony\Component\Workflow\Event\GuardEvent")
     */
    const GUARD = 'workflow.guard';

    /**
     * @Event("Symfony\Component\Workflow\Event\AnnounceEvent")
     */
    const ANNOUNCE = 'workflow.announce';

    /**
     * @Event("Symfony\Component\Workflow\Event\CompletedEvent")
     */
    const COMPLETED = 'workflow.completed';

    /**
     * @Event("Symfony\Component\Workflow\Event\EnterEvent")
     */
    const ENTER = 'workflow.enter';

    /**
     * @Event("Symfony\Component\Workflow\Event\EnteredEvent")
     */
    const ENTERED = 'workflow.entered';

    /**
     * @Event("Symfony\Component\Workflow\Event\LeaveEvent")
     */
    const LEAVE = 'workflow.leave';

    /**
     * @Event("Symfony\Component\Workflow\Event\TransitionEvent")
     */
    const TRANSITION = 'workflow.transition';

    private function __construct()
    {
    }
}
