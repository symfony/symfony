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
    protected $context;
    private object $subject;
    private Marking $marking;
    private ?Transition $transition;
    private ?WorkflowInterface $workflow;

    public function __construct(object $subject, Marking $marking, ?Transition $transition = null, ?WorkflowInterface $workflow = null, array $context = [])
    {
        $this->subject = $subject;
        $this->marking = $marking;
        $this->transition = $transition;
        $this->workflow = $workflow;
        $this->context = $context;
    }

    /**
     * @return Marking
     */
    public function getMarking()
    {
        return $this->marking;
    }

    /**
     * @return object
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return Transition|null
     */
    public function getTransition()
    {
        return $this->transition;
    }

    public function getWorkflow(): WorkflowInterface
    {
        return $this->workflow;
    }

    /**
     * @return string
     */
    public function getWorkflowName()
    {
        return $this->workflow->getName();
    }

    /**
     * @return mixed
     */
    public function getMetadata(string $key, string|Transition|null $subject)
    {
        return $this->workflow->getMetadataStore()->getMetadata($key, $subject);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
