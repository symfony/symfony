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
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\Metadata\MetadataStoreInterface;

/**
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 */
interface WorkflowInterface
{
    /**
     * Returns the object's Marking.
     *
     * @throws LogicException
     */
    public function getMarking(object $subject): Marking;

    /**
     * Returns true if the transition is enabled.
     */
    public function can(object $subject, string $transitionName): bool;

    /**
     * Builds a TransitionBlockerList to know why a transition is blocked.
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

    public function getName(): string;

    public function getDefinition(): Definition;

    public function getMarkingStore(): MarkingStoreInterface;

    public function getMetadataStore(): MetadataStoreInterface;
}
