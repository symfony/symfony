<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Exception;

use Symfony\Component\Workflow\TransitionBlockerList;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Thrown by Workflow when a not enabled transition is applied on a subject.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class NotEnabledTransitionException extends TransitionException
{
    private TransitionBlockerList $transitionBlockerList;

    public function __construct(object $subject, string $transitionName, WorkflowInterface $workflow, TransitionBlockerList $transitionBlockerList, array $context = [])
    {
        parent::__construct($subject, $transitionName, $workflow, sprintf('Transition "%s" is not enabled for workflow "%s".', $transitionName, $workflow->getName()), $context);

        $this->transitionBlockerList = $transitionBlockerList;
    }

    public function getTransitionBlockerList(): TransitionBlockerList
    {
        return $this->transitionBlockerList;
    }
}
