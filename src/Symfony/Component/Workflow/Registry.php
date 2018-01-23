<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

use Symfony\Component\Workflow\Exception\InvalidArgumentException;
use Symfony\Component\Workflow\SupportStrategy\SupportStrategyInterface;
use Symfony\Component\Workflow\SupportStrategy\WorkflowSupportStrategyInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Registry
{
    private $workflows = array();

    /**
     * @param Workflow                 $workflow
     * @param SupportStrategyInterface $supportStrategy
     *
     * @deprecated since Symfony 4.1, to be removed in 5.0. Use addWorkflow() instead.
     */
    public function add(Workflow $workflow, $supportStrategy)
    {
        @trigger_error(sprintf('%s is deprecated since Symfony 4.1. Use addWorkflow() instead.', __METHOD__), E_USER_DEPRECATED);
        $this->workflows[] = array($workflow, $supportStrategy);
    }

    public function addWorkflow(WorkflowInterface $workflow, WorkflowSupportStrategyInterface $supportStrategy)
    {
        $this->workflows[] = array($workflow, $supportStrategy);
    }

    /**
     * @param object      $subject
     * @param string|null $workflowName
     *
     * @return Workflow
     */
    public function get($subject, $workflowName = null)
    {
        $matched = null;

        foreach ($this->workflows as list($workflow, $supportStrategy)) {
            if ($this->supports($workflow, $supportStrategy, $subject, $workflowName)) {
                if ($matched) {
                    throw new InvalidArgumentException('At least two workflows match this subject. Set a different name on each and use the second (name) argument of this method.');
                }
                $matched = $workflow;
            }
        }

        if (!$matched) {
            throw new InvalidArgumentException(sprintf('Unable to find a workflow for class "%s".', get_class($subject)));
        }

        return $matched;
    }

    private function supports(WorkflowInterface $workflow, $supportStrategy, $subject, $workflowName): bool
    {
        if (null !== $workflowName && $workflowName !== $workflow->getName()) {
            return false;
        }

        return $supportStrategy->supports($workflow, $subject);
    }
}
