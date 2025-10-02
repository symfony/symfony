<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Workflow;

use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\WorkflowTrait;

#[AsWorkflow(supports: Ticket::class, markingProperty: 'status')]
class TicketWorkflow
{
    use WorkflowTrait;

    #[Transition(from: TicketStatus::Open, to: TicketStatus::Resolved)]
    public const RESOLVE = 'resolve';

    #[Transition(from: TicketStatus::Resolved, to: TicketStatus::Closed)]
    public const CLOSE = 'close';

    #[Transition(from: TicketStatus::Resolved, to: TicketStatus::Open)]
    #[Transition(from: TicketStatus::Closed, to: TicketStatus::Open)]
    public const REOPEN = 'reopen';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function resolve(Ticket $ticket): Marking
    {
        $this->logger->info('Resolving a ticket.');

        return $this->apply($ticket, self::RESOLVE);
    }

    #[AsGuardListener(transition: self::CLOSE)]
    public function guardClose(GuardEvent $event): void
    {
        if ($event->getSubject()->locked) {
            $event->setBlocked(true, 'The ticket is locked.');
        }
    }
}
