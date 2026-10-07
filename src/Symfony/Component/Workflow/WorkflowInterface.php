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
 * Applies transitions to a subject, and tells which ones are enabled or blocked.
 *
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 */
interface WorkflowInterface
{
    /**
     * Returns the object's Marking.
     *
     * @param array<string, mixed> $context The context of the "entered" event dispatched when the subject gets its initial marking
     *
     * @throws LogicException
     */
    public function getMarking(object $subject/* , array $context = [] */): Marking;

    /**
     * Returns true if the transition is enabled.
     */
    public function can(object $subject, string $transitionName): bool;

    /**
     * Builds a TransitionBlockerList to know why a transition is blocked.
     *
     * @throws UndefinedTransitionException If the transition is not defined
     */
    public function buildTransitionBlockerList(object $subject, string $transitionName): TransitionBlockerList;

    /**
     * Fire a transition.
     *
     * @throws LogicException If the transition is not applicable
     */
    public function apply(object $subject, string $transitionName, array $context = []): Marking;

    /**
     * Returns all enabled transitions.
     *
     * @return Transition[]
     */
    public function getEnabledTransitions(object $subject): array;

    public function getEnabledTransition(object $subject, string $name): ?Transition;

    public function getName(): string;

    public function getDefinition(): Definition;

    public function getMarkingStore(): MarkingStoreInterface;

    public function getMetadataStore(): MetadataStoreInterface;
}
