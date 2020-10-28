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
    private $workflows = [];

    /**
     * @param SupportStrategyInterface $supportStrategy
     *
     * @deprecated since Symfony 4.1, use addWorkflow() instead
     */
    public function add(Workflow $workflow, $supportStrategy)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.1. Use addWorkflow() instead.', __METHOD__), \E_USER_DEPRECATED);
        $this->workflows[] = [$workflow, $supportStrategy];
    }

    public function addWorkflow(WorkflowInterface $workflow, WorkflowSupportStrategyInterface $supportStrategy)
    {
        $this->workflows[] = [$workflow, $supportStrategy];
    }

    /**
     * @param object      $subject
     * @param string|null $workflowName
     *
     * @return Workflow
     */
    public function get($subject, $workflowName = null)
    {
        $matched = [];

        foreach ($this->workflows as [$workflow, $supportStrategy]) {
            if ($this->supports($workflow, $supportStrategy, $subject, $workflowName)) {
                $matched[] = $workflow;
            }
        }

        if (!$matched) {
            throw new InvalidArgumentException(sprintf('Unable to find a workflow for class "%s".', \get_class($subject)));
        }

        if (2 <= \count($matched)) {
            $names = array_map(static function (WorkflowInterface $workflow): string {
                return $workflow->getName();
            }, $matched);

            throw new InvalidArgumentException(sprintf('Too many workflows (%s) match this subject (%s); set a different name on each and use the second (name) argument of this method.', implode(', ', $names), \get_class($subject)));
        }

        return $matched[0];
    }

    /**
     * @param object $subject
     *
     * @return Workflow[]
     */
    public function all($subject): array
    {
        $matched = [];
        foreach ($this->workflows as [$workflow, $supportStrategy]) {
            if ($supportStrategy->supports($workflow, $subject)) {
                $matched[] = $workflow;
            }
        }

        return $matched;
    }

    /**
     * @param WorkflowSupportStrategyInterface $supportStrategy
     * @param object                           $subject
     */
    private function supports(WorkflowInterface $workflow, $supportStrategy, $subject, ?string $workflowName): bool
    {
        if (null !== $workflowName && $workflowName !== $workflow->getName()) {
            return false;
        }

        return $supportStrategy->supports($workflow, $subject);
    }
}
