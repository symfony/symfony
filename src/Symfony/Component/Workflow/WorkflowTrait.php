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

use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Exception\UndefinedTransitionException;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\Metadata\MetadataStoreInterface;

/**
 * Exposes the methods of a workflow in the class defining it with the AsWorkflow attribute.
 *
 * The workflow is injected by the container; the class is free to declare
 * any constructor, custom methods, and listeners.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
trait WorkflowTrait
{
    private readonly WorkflowInterface $workflow;

    /**
     * Called by the container, once the workflow is registered.
     *
     * @internal
     */
    public function setWorkflow(WorkflowInterface $workflow): void
    {
        $this->workflow = $workflow;
    }

    /**
     * Returns the object's Marking.
     *
     * @throws LogicException
     */
    public function getMarking(object $subject, array $context = []): Marking
    {
        return $this->getWorkflow()->getMarking($subject, $context);
    }

    /**
     * Returns true if the transition is enabled.
     */
    public function can(object $subject, string $transitionName): bool
    {
        return $this->getWorkflow()->can($subject, $transitionName);
    }

    /**
     * Builds a TransitionBlockerList to know why a transition is blocked.
     *
     * @throws UndefinedTransitionException If the transition is not defined
     */
    public function buildTransitionBlockerList(object $subject, string $transitionName): TransitionBlockerList
    {
        return $this->getWorkflow()->buildTransitionBlockerList($subject, $transitionName);
    }

    /**
     * Fire a transition.
     *
     * @throws LogicException If the transition is not applicable
     */
    public function apply(object $subject, string $transitionName, array $context = []): Marking
    {
        return $this->getWorkflow()->apply($subject, $transitionName, $context);
    }

    /**
     * Returns all enabled transitions.
     *
     * @return Transition[]
     */
    public function getEnabledTransitions(object $subject): array
    {
        return $this->getWorkflow()->getEnabledTransitions($subject);
    }

    public function getEnabledTransition(object $subject, string $name): ?Transition
    {
        return $this->getWorkflow()->getEnabledTransition($subject, $name);
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

    private function getWorkflow(): WorkflowInterface
    {
        return $this->workflow ?? throw new LogicException(\sprintf('The workflow of "%s" has not been set: is the class registered as a service, and does it use the "#[%s]" attribute?', self::class, Attribute\AsWorkflow::class));
    }
}
