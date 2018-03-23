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

use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Thrown by Workflow when an undefined transition is applied on a subject.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class UndefinedTransitionException extends TransitionException
{
    public function __construct($subject, string $transitionName, WorkflowInterface $workflow)
    {
        parent::__construct($subject, $transitionName, $workflow, sprintf('Transition "%s" is not defined for workflow "%s".', $transitionName, $workflow->getName()));
    }
}
