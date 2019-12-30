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
use Symfony\Component\Workflow\SupportStrategy\WorkflowSupportStrategyInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Registry
{
    private $workflows = [];

    public function addWorkflow(WorkflowInterface $workflow, WorkflowSupportStrategyInterface $supportStrategy)
    {
        $this->workflows[] = [$workflow, $supportStrategy];
    }

    /**
     * @return Workflow
     */
    public function get(object $subject, string $workflowName = null)
    {
        $matched = [];

        foreach ($this->workflows as list($workflow, $supportStrategy)) {
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
     * @return Workflow[]
     */
    public function all(object $subject): array
    {
        $matched = [];
        foreach ($this->workflows as list($workflow, $supportStrategy)) {
            if ($supportStrategy->supports($workflow, $subject)) {
                $matched[] = $workflow;
            }
        }

        return $matched;
    }

    private function supports(WorkflowInterface $workflow, WorkflowSupportStrategyInterface $supportStrategy, object $subject, ?string $workflowName): bool
    {
        if (null !== $workflowName && $workflowName !== $workflow->getName()) {
            return false;
        }

        return $supportStrategy->supports($workflow, $subject);
    }
}
