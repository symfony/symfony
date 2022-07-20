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
     * @param object $subject A subject
     *
     * @return Marking The Marking
     *
     * @throws LogicException
     */
    public function getMarking($subject);

    /**
     * Returns true if the transition is enabled.
     *
     * @param object $subject        A subject
     * @param string $transitionName A transition
     *
     * @return bool true if the transition is enabled
     */
    public function can($subject, $transitionName);

    /**
     * Builds a TransitionBlockerList to know why a transition is blocked.
     *
     * @param object $subject A subject
     */
    public function buildTransitionBlockerList($subject, string $transitionName): TransitionBlockerList;

    /**
     * Fire a transition.
     *
     * @param object $subject        A subject
     * @param string $transitionName A transition
     * @param array  $context        Some context
     *
     * @return Marking The new Marking
     *
     * @throws LogicException If the transition is not applicable
     */
    public function apply($subject, $transitionName/* , array $context = [] */);

    /**
     * Returns all enabled transitions.
     *
     * @param object $subject A subject
     *
     * @return Transition[] All enabled transitions
     */
    public function getEnabledTransitions($subject);

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
