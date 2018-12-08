<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * WorkflowExtension.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class WorkflowExtension extends AbstractExtension
{
    private $workflowRegistry;

    public function __construct(Registry $workflowRegistry)
    {
        $this->workflowRegistry = $workflowRegistry;
    }

    public function getFunctions()
    {
        $functions = array(
            new TwigFunction('workflow_can', array($this, 'canTransition')),
            new TwigFunction('workflow_transitions', array($this, 'getEnabledTransitions')),
            new TwigFunction('workflow_has_marked_place', array($this, 'hasMarkedPlace')),
            new TwigFunction('workflow_marked_places', array($this, 'getMarkedPlaces')),
            new TwigFunction('workflow_metadata', array($this, 'getMetadata')),
        );

        if (method_exists(Workflow::class, 'could')) {
            $functions[] = new TwigFunction('workflow_could', array($this, 'couldTransition'));
        }

        if (method_exists(Workflow::class, 'getPossibleTransitions')) {
            $functions[] = new TwigFunction('workflow_possible_transitions', array($this, 'getPossibleTransitions'));
        }

        return $functions;
    }

    /**
     * Returns true if the transition is enabled.
     *
     * @param object $subject        A subject
     * @param string $transitionName A transition
     * @param string $name           A workflow name
     *
     * @return bool true if the transition is enabled
     */
    public function canTransition($subject, $transitionName, $name = null)
    {
        return $this->workflowRegistry->get($subject, $name)->can($subject, $transitionName);
    }

    /**
     * Returns true if there is a transition which is defined for the state.
     *
     * @param object $subject        A subject
     * @param string $transitionName A transition
     * @param string $name           A workflow name
     *
     * @return bool true if there is a transition which is defined for the state
     */
    public function couldTransition($subject, $transitionName, $name = null)
    {
        return $this->workflowRegistry->get($subject, $name)->could($subject, $transitionName);
    }

    /**
     * Returns all enabled transitions.
     *
     * @param object $subject A subject
     * @param string $name    A workflow name
     *
     * @return Transition[] All enabled transitions
     */
    public function getEnabledTransitions($subject, $name = null)
    {
        return $this->workflowRegistry->get($subject, $name)->getEnabledTransitions($subject);
    }

    /**
     * Returns all transitions defined for the state.
     *
     * @param object $subject A subject
     * @param string $name    A workflow name
     *
     * @return Transition[] All defined transitions for the state
     */
    public function getPossibleTransitions($subject, $name = null)
    {
        return $this->workflowRegistry->get($subject, $name)->getPossibleTransitions($subject);
    }

    /**
     * Returns true if the place is marked.
     *
     * @param object $subject   A subject
     * @param string $placeName A place name
     * @param string $name      A workflow name
     *
     * @return bool true if the transition is enabled
     */
    public function hasMarkedPlace($subject, $placeName, $name = null)
    {
        return $this->workflowRegistry->get($subject, $name)->getMarking($subject)->has($placeName);
    }

    /**
     * Returns marked places.
     *
     * @param object $subject        A subject
     * @param bool   $placesNameOnly If true, returns only places name. If false returns the raw representation
     * @param string $name           A workflow name
     *
     * @return string[]|int[]
     */
    public function getMarkedPlaces($subject, $placesNameOnly = true, $name = null)
    {
        $places = $this->workflowRegistry->get($subject, $name)->getMarking($subject)->getPlaces();

        if ($placesNameOnly) {
            return array_keys($places);
        }

        return $places;
    }

    /**
     * Returns the metadata for a specific subject.
     *
     * @param object                 $subject         A subject
     * @param string|Transition|null $metadataSubject Use null to get workflow metadata
     *                                                Use a string (the place name) to get place metadata
     *                                                Use a Transition instance to get transition metadata
     */
    public function getMetadata($subject, string $key, $metadataSubject = null, string $name = null): ?string
    {
        return $this
            ->workflowRegistry
            ->get($subject, $name)
            ->getMetadataStore()
            ->getMetadata($key, $metadataSubject)
        ;
    }

    public function getName()
    {
        return 'workflow';
    }
}
