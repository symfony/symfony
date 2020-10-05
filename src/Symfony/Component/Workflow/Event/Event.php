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
    private $subject;
    private $marking;
    private $transition;
    private $workflow;

    public function __construct(object $subject, Marking $marking, Transition $transition = null, WorkflowInterface $workflow = null, array $context = [])
    {
        $this->subject = $subject;
        $this->marking = $marking;
        $this->transition = $transition;
        $this->workflow = $workflow;
        $this->context = $context;
    }

    public function getMarking()
    {
        return $this->marking;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getTransition()
    {
        return $this->transition;
    }

    public function getWorkflow(): WorkflowInterface
    {
        return $this->workflow;
    }

    public function getWorkflowName()
    {
        return $this->workflow->getName();
    }

    public function getMetadata(string $key, $subject)
    {
        return $this->workflow->getMetadataStore()->getMetadata($key, $subject);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
