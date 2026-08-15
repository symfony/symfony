<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow;

use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\LogicException;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class FormFlowCursor
{
    /** @var list<StepFlowNode> */
    private array $roots;
    /** @var array<string, StepFlowNode> */
    private array $stepMap;
    /** @var list<string> */
    private array $steps;
    private StepFlowNode $currentStep;

    /**
     * @param array<string, StepFlowConfigInterface>|list<string> $steps       Step configs or a flat list of step names
     * @param string                                              $currentStep The name of the current step
     */
    public function __construct(
        array $steps,
        string $currentStep,
    ) {
        $first = reset($steps);

        if (\is_string($first)) {
            $this->roots = StepFlowNode::fromArray($steps);
        } elseif ($first instanceof StepFlowConfigInterface) {
            $this->roots = StepFlowNode::fromConfig($steps);
        } else {
            throw new InvalidArgumentException('The $steps argument must be a list of step names or a list of step configs.');
        }

        // Performs a DFS pre-order traversal to build flat step names and node lookup map.
        $this->stepMap = [];
        $this->steps = [];
        $stack = array_reverse($this->roots);
        while ($stack) {
            $node = array_pop($stack);
            $this->stepMap[$node->getName()] = $node;
            $this->steps[] = $node->getName();
            foreach (array_reverse($node->getChildren()) as $child) {
                $stack[] = $child;
            }
        }

        if (!isset($this->stepMap[$currentStep])) {
            throw new InvalidArgumentException(\sprintf('Step "%s" does not exist. Available steps are: "%s".', $currentStep, implode('", "', $this->steps)));
        }

        $this->currentStep = $this->stepMap[$currentStep];
    }

    /**
     * Returns the flattened list of steps in DFS pre-order.
     *
     * @return list<string>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getTotalSteps(): int
    {
        return \count($this->steps);
    }

    /**
     * Returns the global index of the current step among all flattened steps.
     */
    public function getStepIndex(): int
    {
        return $this->getStepIndexOf($this->currentStep->getName());
    }

    public function getStepIndexOf(string $name): int
    {
        return array_search($name, $this->steps, true);
    }

    public function getParentStep(): ?string
    {
        return $this->currentStep->getParent()?->getName();
    }

    /**
     * @return list<string>
     */
    public function getChildSteps(): array
    {
        return array_map(static fn (StepFlowNode $node) => $node->getName(), $this->currentStep->getChildren());
    }

    public function getFirstStep(): string
    {
        foreach ($this->steps as $name) {
            if (!$this->stepMap[$name]->isGroup()) {
                return $name;
            }
        }

        throw new LogicException('No visitable step found.');
    }

    public function getPreviousStep(): ?string
    {
        return $this->currentStep->getPreviousInTraversal()?->getName();
    }

    public function getCurrentStep(): string
    {
        return $this->currentStep->getName();
    }

    public function withCurrentStep(string $step): self
    {
        if (!isset($this->stepMap[$step])) {
            throw new InvalidArgumentException(\sprintf('Step "%s" does not exist. Available steps are: "%s".', $step, implode('", "', $this->steps)));
        }

        $clone = clone $this;
        $clone->currentStep = $this->stepMap[$step];

        return $clone;
    }

    public function getNextStep(): ?string
    {
        return $this->currentStep->getNextInTraversal()?->getName();
    }

    public function getLastStep(): string
    {
        for ($i = \count($this->steps) - 1; $i >= 0; --$i) {
            if (!$this->stepMap[$this->steps[$i]]->isGroup()) {
                return $this->steps[$i];
            }
        }

        throw new LogicException('No visitable step found.');
    }

    public function isFirstStep(): bool
    {
        $node = $this->currentStep;
        while (null !== $prev = $node->getPreviousInTraversal()) {
            if (!$prev->isGroup()) {
                return false;
            }
            $node = $prev;
        }

        return true;
    }

    public function isLastStep(): bool
    {
        $node = $this->currentStep;
        while (null !== $next = $node->getNextInTraversal()) {
            if (!$next->isGroup()) {
                return false;
            }
            $node = $next;
        }

        return true;
    }

    public function canMoveBack(): bool
    {
        $node = $this->currentStep;
        while (null !== $prev = $node->getPreviousInTraversal()) {
            if (!$prev->isGroup()) {
                return true;
            }
            $node = $prev;
        }

        return false;
    }

    public function canMoveNext(): bool
    {
        $node = $this->currentStep;
        while (null !== $next = $node->getNextInTraversal()) {
            if (!$next->isGroup()) {
                return true;
            }
            $node = $next;
        }

        return false;
    }

    public function getCurrentStepNode(): StepFlowNode
    {
        return $this->currentStep;
    }

    public function getStepNode(string $name): StepFlowNode
    {
        if (!isset($this->stepMap[$name])) {
            throw new InvalidArgumentException(\sprintf('Step "%s" does not exist. Available steps are: "%s".', $name, implode('", "', $this->steps)));
        }

        return $this->stepMap[$name];
    }

    /**
     * @return list<StepFlowNode>
     */
    public function getRootStepNodes(): array
    {
        return $this->roots;
    }
}
