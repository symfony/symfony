<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow\Exception;

use Symphony\Component\Workflow\TransitionBlockerList;
use Symphony\Component\Workflow\WorkflowInterface;

/**
 * Thrown by Workflow when a not enabled transition is applied on a subject.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class NotEnabledTransitionException extends TransitionException
{
    private $transitionBlockerList;

    public function __construct($subject, string $transitionName, WorkflowInterface $workflow, TransitionBlockerList $transitionBlockerList)
    {
        parent::__construct($subject, $transitionName, $workflow, sprintf('Transition "%s" is not enabled for workflow "%s".', $transitionName, $workflow->getName()));

        $this->transitionBlockerList = $transitionBlockerList;
    }

    public function getTransitionBlockerList(): TransitionBlockerList
    {
        return $this->transitionBlockerList;
    }
}
