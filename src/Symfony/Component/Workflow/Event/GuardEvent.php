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
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\TransitionBlockerList;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @final since Symfony 4.4
 */
class GuardEvent extends Event
{
    private $transitionBlockerList;

    /**
     * {@inheritdoc}
     */
    public function __construct($subject, Marking $marking, Transition $transition, $workflowName = null)
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
            $this->transitionBlockerList->clear();

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
