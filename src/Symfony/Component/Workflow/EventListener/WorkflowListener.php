<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\EventListener;

use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * @author Jules Pietri <jules@heahprod.com>
 */
class WorkflowListener
{
    public function __invoke(GuardEvent $event)
    {
        $marking = $event->getMarking();

        foreach ($event->getTransition()->getTos() as $to) {
            if (!$marking->has($to)) {
                return;
            }
        }

        $event->setBlocked(true);
    }
}
