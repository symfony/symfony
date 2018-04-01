<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow\Event;

use Symphony\Component\Workflow\Marking;
use Symphony\Component\Workflow\Transition;
use Symphony\Component\Workflow\TransitionBlocker;
use Symphony\Component\Workflow\TransitionBlockerList;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class GuardEvent extends Event
{
    private $transitionBlockerList;

    /**
     * {@inheritdoc}
     */
    public function __construct($subject, Marking $marking, Transition $transition, $workflowName = 'unnamed')
    {
        parent::__construct($subject, $marking, $transition, $workflowName);

        $this->transitionBlockerList = new TransitionBlockerList();
    }

    public function isBlocked()
    {
        return !$this->transitionBlockerList->isEmpty();
    }

    public function setBlocked($blocked)
    {
        if (!$blocked) {
            $this->transitionBlockerList->reset();

            return;
        }

        $this->transitionBlockerList->add(TransitionBlocker::createUnknown());
    }

    public function getTransitionBlockerList(): TransitionBlockerList
    {
        return $this->transitionBlockerList;
    }

    public function addTransitionBlocker(TransitionBlocker $transitionBlocker): void
    {
        $this->transitionBlockerList->add($transitionBlocker);
    }
}
