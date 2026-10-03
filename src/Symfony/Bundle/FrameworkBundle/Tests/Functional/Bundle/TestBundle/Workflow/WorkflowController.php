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

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Workflow\WorkflowInterface;

class WorkflowController
{
    public function __construct(
        private readonly TicketWorkflow $ticketWorkflow,
        #[Target('article')]
        private readonly WorkflowInterface $articleWorkflow,
    ) {
    }

    public function __invoke(): Response
    {
        $ticket = new Ticket();
        $steps = [$ticket->status->value];
        $this->ticketWorkflow->resolve($ticket);
        $steps[] = $ticket->status->value;
        $steps[] = $this->ticketWorkflow->can($ticket, TicketWorkflow::CLOSE) ? 'closable' : 'not-closable';
        $ticket->locked = true;
        $steps[] = $this->ticketWorkflow->can($ticket, TicketWorkflow::CLOSE) ? 'closable' : 'locked';
        $steps[] = $this->ticketWorkflow->getMetadataStore()->getMetadata('label', $ticket->status->value);

        $article = new Article();
        $this->articleWorkflow->apply($article, 'publish');

        return new Response(implode('|', $steps).';'.implode(',', array_keys($article->marking)));
    }
}
