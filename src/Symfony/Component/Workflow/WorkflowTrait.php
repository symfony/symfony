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

use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\Metadata\MetadataStoreInterface;

trait WorkflowTrait
{
    /** @internal */
    private readonly WorkflowInterface $workflow;

    public function setWorkflow(WorkflowInterface $workflow): void
    {
        $this->workflow = $workflow;
    }

    public function getMarking(object $subject): Marking
    {
        return $this->getWorkflow()->getMarking($subject);
    }

    public function can(object $subject, string $transitionName): bool
    {
        return $this->getWorkflow()->can($subject, $transitionName);
    }

    public function buildTransitionBlockerList(object $subject, string $transitionName): TransitionBlockerList
    {
        return $this->getWorkflow()->buildTransitionBlockerList($subject, $transitionName);
    }

    public function apply(object $subject, string $transitionName, array $context = []): Marking
    {
        return $this->getWorkflow()->apply($subject, $transitionName, $context);
    }

    public function getEnabledTransitions(object $subject): array
    {
        return $this->getWorkflow()->getEnabledTransitions($subject);
    }

    public function getName(): string
    {
        return $this->getWorkflow()->getName();
    }

    public function getDefinition(): Definition
    {
        return $this->getWorkflow()->getDefinition();
    }

    public function getMarkingStore(): MarkingStoreInterface
    {
        return $this->getWorkflow()->getMarkingStore();
    }

    public function getMetadataStore(): MetadataStoreInterface
    {
        return $this->getWorkflow()->getMetadataStore();
    }

    /** @internal */
    private function getWorkflow(): WorkflowInterface
    {
        if (!isset($this->workflow)) {
            throw new \LogicException(\sprintf('The workflow has not been set. Did you forget to call "%s::setWorkflow()"?', __CLASS__));
        }

        return $this->workflow;
    }
}
