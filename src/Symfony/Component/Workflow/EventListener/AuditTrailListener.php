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
use Symfony\Component\Workflow\Event\Event;

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

    /**
     * @return void
     */
    public function onLeave(Event $event)
    {
        foreach ($event->getTransition()->getFroms() as $place) {
            $this->logger->info(sprintf('Leaving "%s" for subject of class "%s" in workflow "%s".', $place, $event->getSubject()::class, $event->getWorkflowName()));
        }
    }

    /**
     * @return void
     */
    public function onTransition(Event $event)
    {
        $this->logger->info(sprintf('Transition "%s" for subject of class "%s" in workflow "%s".', $event->getTransition()->getName(), $event->getSubject()::class, $event->getWorkflowName()));
    }

    /**
     * @return void
     */
    public function onEnter(Event $event)
    {
        foreach ($event->getTransition()->getTos() as $place) {
            $this->logger->info(sprintf('Entering "%s" for subject of class "%s" in workflow "%s".', $place, $event->getSubject()::class, $event->getWorkflowName()));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.leave' => ['onLeave'],
            'workflow.transition' => ['onTransition'],
            'workflow.enter' => ['onEnter'],
        ];
    }
}
