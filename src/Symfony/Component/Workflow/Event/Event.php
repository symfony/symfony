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

use Symfony\Component\EventDispatcher\Event as BaseEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Event extends BaseEvent
{
    private $subject;
    private $marking;
    private $transition;
    private $workflow;
    private $workflowName;

    /**
     * @param object $subject
     */
    public function __construct($subject, Marking $marking, Transition $transition = null, $workflow = null)
    {
        $this->subject = $subject;
        $this->marking = $marking;
        $this->transition = $transition;
        if (null === $workflow) {
            @trigger_error(sprintf('Passing only three parameters to "%s" is deprecated since Symfony 4.1. Pass a %s instance as fourth parameter instead.', __METHOD__, WorkflowInterface::class), \E_USER_DEPRECATED);
            $this->workflowName = 'unnamed';
        } elseif (\is_string($workflow)) {
            @trigger_error(sprintf('Passing a string as the 4th parameter of "%s()" is deprecated since Symfony 4.1. Pass a %s instance instead.', __METHOD__, WorkflowInterface::class), \E_USER_DEPRECATED);
            $this->workflowName = $workflow;
        } elseif ($workflow instanceof WorkflowInterface) {
            $this->workflow = $workflow;
        } else {
            throw new \TypeError(sprintf('The 4th parameter of "%s"  should be a "%s" instance instead.', __METHOD__, WorkflowInterface::class));
        }
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
        // BC layer
        if (!$this->workflow instanceof WorkflowInterface) {
            throw new \RuntimeException(sprintf('The 4th parameter of "%s"::__construct() should be a "%s" instance.', __CLASS__, WorkflowInterface::class));
        }

        return $this->workflow;
    }

    public function getWorkflowName()
    {
        // BC layer
        if ($this->workflowName) {
            return $this->workflowName;
        }

        // BC layer
        if (!$this->workflow instanceof WorkflowInterface) {
            throw new \RuntimeException(sprintf('The 4th parameter of "%s"::__construct() should be a "%s" instance.', __CLASS__, WorkflowInterface::class));
        }

        return $this->workflow->getName();
    }

    public function getMetadata(string $key, $subject)
    {
        // BC layer
        if (!$this->workflow instanceof WorkflowInterface) {
            throw new \RuntimeException(sprintf('The 4th parameter of "%s"::__construct() should be a "%s" instance.', __CLASS__, WorkflowInterface::class));
        }

        return $this->workflow->getMetadataStore()->getMetadata($key, $subject);
    }
}
