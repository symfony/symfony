<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Event;

use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\EventDispatcher\Event as BaseEvent;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Carlos Pereira De Amorim <carlos@shauri.fr>
 */
class Event extends BaseEvent
{
    public function __construct(
        private object $subject,
        private Marking $marking,
        private ?Transition $transition = null,
        private ?WorkflowInterface $workflow = null,
    ) {
    }

    public function getMarking(): Marking
    {
        return $this->marking;
    }

    public function getSubject(): object
    {
        return $this->subject;
    }

    public function getTransition(): ?Transition
    {
        return $this->transition;
    }

    public function getWorkflow(): WorkflowInterface
    {
        return $this->workflow;
    }

    public function getWorkflowName(): string
    {
        return $this->workflow->getName();
    }

    public function getMetadata(string $key, string|Transition|null $subject): mixed
    {
        return $this->workflow->getMetadataStore()->getMetadata($key, $subject);
    }
}
