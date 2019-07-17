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
     * @return Marking The Marking
     *
     * @throws LogicException
     */
    public function getMarking(object $subject);

    /**
     * Returns true if the transition is enabled.
     *
     * @return bool true if the transition is enabled
     */
    public function can(object $subject, string $transitionName);

    /**
     * Builds a TransitionBlockerList to know why a transition is blocked.
     */
    public function buildTransitionBlockerList(object $subject, string $transitionName): TransitionBlockerList;

    /**
     * Fire a transition.
     *
     * @return Marking The new Marking
     *
     * @throws LogicException If the transition is not applicable
     */
    public function apply(object $subject, string $transitionName, array $context = []);

    /**
     * Returns all enabled transitions.
     *
     * @return Transition[] All enabled transitions
     */
    public function getEnabledTransitions(object $subject);

    /**
     * @return string
     */
    public function getName();

    /**
     * @return Definition
     */
    public function getDefinition();

    /**
     * @return MarkingStoreInterface
     */
    public function getMarkingStore();

    public function getMetadataStore(): MetadataStoreInterface;
}
