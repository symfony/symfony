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

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Event\LeaveEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\WorkflowEvents;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class AuditTrailListener implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onLeave(LeaveEvent $event)
    {
        foreach ($event->getTransition()->getFroms() as $place) {
            $this->logger->info(sprintf('Leaving "%s" for subject of class "%s" in workflow "%s".', $place, \get_class($event->getSubject()), $event->getWorkflowName()));
        }
    }

    public function onTransition(TransitionEvent $event)
    {
        $this->logger->info(sprintf('Transition "%s" for subject of class "%s" in workflow "%s".', $event->getTransition()->getName(), \get_class($event->getSubject()), $event->getWorkflowName()));
    }

    public function onEnter(EnterEvent $event)
    {
        foreach ($event->getTransition()->getTos() as $place) {
            $this->logger->info(sprintf('Entering "%s" for subject of class "%s" in workflow "%s".', $place, \get_class($event->getSubject()), $event->getWorkflowName()));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkflowEvents::LEAVE => ['onLeave'],
            WorkflowEvents::TRANSITION => ['onTransition'],
            WorkflowEvents::ENTER => ['onEnter'],
        ];
    }
}
