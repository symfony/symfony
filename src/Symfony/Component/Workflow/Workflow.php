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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\UniqueTransitionOutputInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Workflow
{
    private $definition;
    private $markingStore;
    private $dispatcher;
    private $name;

    public function __construct(Definition $definition, MarkingStoreInterface $markingStore, EventDispatcherInterface $dispatcher = null, $name = 'unnamed')
    {
        $this->definition = $definition;
        $this->markingStore = $markingStore;
        $this->dispatcher = $dispatcher;
        $this->name = $name;

        // If the marking can contain only one place, we should control the definition
        if ($markingStore instanceof UniqueTransitionOutputInterface) {
            foreach ($definition->getTransitions() as $transition) {
                if (1 < count($transition->getTos())) {
                    throw new LogicException(sprintf('The marking store (%s) of workflow "%s" can not store many places. But the transition "%s" has too many output (%d). Only one is accepted.', get_class($markingStore), $this->name, $transition->getName(), count($transition->getTos())));
                }
            }
        }
    }

    /**
     * Returns the object's Marking.
     *
     * @param object $subject A subject
     *
     * @return Marking The Marking
     *
     * @throws LogicException
     */
    public function getMarking($subject)
    {
        $marking = $this->markingStore->getMarking($subject);

        if (!$marking instanceof Marking) {
            throw new LogicException(sprintf('The value returned by the MarkingStore is not an instance of "%s" for workflow "%s".', Marking::class, $this->name));
        }

        // check if the subject is already in the workflow
        if (!$marking->getPlaces()) {
            if (!$this->definition->getInitialPlace()) {
                throw new LogicException(sprintf('The Marking is empty and there is no initial place for workflow "%s".', $this->name));
            }
            $marking->mark($this->definition->getInitialPlace());
        }

        // check that the subject has a known place
        $places = $this->definition->getPlaces();
        foreach ($marking->getPlaces() as $placeName => $nbToken) {
            if (!isset($places[$placeName])) {
                $message = sprintf('Place "%s" is not valid for workflow "%s".', $placeName, $this->name);
                if (!$places) {
                    $message .= ' It seems you forgot to add places to the current workflow.';
                }

                throw new LogicException($message);
            }
        }

        // Because the marking could have been initialized, we update the subject
        $this->markingStore->setMarking($subject, $marking);

        return $marking;
    }

    /**
     * Returns true if the transition is enabled.
     *
     * @param object $subject        A subject
     * @param string $transitionName A transition
     *
     * @return bool true if the transition is enabled
     *
     * @throws LogicException If the transition does not exist
     */
    public function can($subject, $transitionName)
    {
        $transitions = $this->definition->getTransitions();

        if (!isset($transitions[$transitionName])) {
            throw new LogicException(sprintf('Transition "%s" does not exist for workflow "%s".', $transitionName, $this->name));
        }

        $transition = $transitions[$transitionName];
        $marking = $this->getMarking($subject);

        return $this->doCan($subject, $marking, $transition);
    }

    /**
     * Fire a transition.
     *
     * @param object $subject        A subject
     * @param string $transitionName A transition
     *
     * @return Marking The new Marking
     *
     * @throws LogicException If the transition is not applicable
     * @throws LogicException If the transition does not exist
     */
    public function apply($subject, $transitionName)
    {
        if (!$this->can($subject, $transitionName)) {
            throw new LogicException(sprintf('Unable to apply transition "%s" for workflow "%s".', $transitionName, $this->name));
        }

        // We can shortcut the getMarking method in order to boost performance,
        // since the "can" method already checks the Marking state
        $marking = $this->markingStore->getMarking($subject);
        $transition = $this->definition->getTransitions()[$transitionName];

        $this->leave($subject, $transition, $marking);

        $this->transition($subject, $transition, $marking);

        $this->enter($subject, $transition, $marking);

        $this->announce($subject, $transition, $marking);

        $this->markingStore->setMarking($subject, $marking);

        return $marking;
    }

    /**
     * Returns all enabled transitions.
     *
     * @param object $subject A subject
     *
     * @return Transition[] All enabled transitions
     */
    public function getEnabledTransitions($subject)
    {
        $enabled = array();
        $marking = $this->getMarking($subject);

        foreach ($this->definition->getTransitions() as $transition) {
            if ($this->doCan($subject, $marking, $transition)) {
                $enabled[$transition->getName()] = $transition;
            }
        }

        return $enabled;
    }

    public function getName()
    {
        return $this->name;
    }    
    
    /** 
    *@return Definition
    */
    public function getDefinition()
    {
        return $this->definition;
    }


    private function doCan($subject, Marking $marking, Transition $transition)
    {
        foreach ($transition->getFroms() as $place) {
            if (!$marking->has($place)) {
                return false;
            }
        }

        if (true === $this->guardTransition($subject, $marking, $transition)) {
            return false;
        }

        return true;
    }

    private function guardTransition($subject, Marking $marking, Transition $transition)
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new GuardEvent($subject, $marking, $transition);

        $this->dispatcher->dispatch('workflow.guard', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.guard', $this->name), $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.guard.%s', $this->name, $transition->getName()), $event);

        return $event->isBlocked();
    }

    private function leave($subject, Transition $transition, Marking $marking)
    {
        if (null !== $this->dispatcher) {
            $event = new Event($subject, $marking, $transition);

            $this->dispatcher->dispatch('workflow.leave', $event);
            $this->dispatcher->dispatch(sprintf('workflow.%s.leave', $this->name), $event);
        }

        foreach ($transition->getFroms() as $place) {
            $marking->unmark($place);

            if (null !== $this->dispatcher) {
                $this->dispatcher->dispatch(sprintf('workflow.%s.leave.%s', $this->name, $place), $event);
            }
        }
    }

    private function transition($subject, Transition $transition, Marking $marking)
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new Event($subject, $marking, $transition);

        $this->dispatcher->dispatch('workflow.transition', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.transition', $this->name), $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.transition.%s', $this->name, $transition->getName()), $event);
    }

    private function enter($subject, Transition $transition, Marking $marking)
    {
        if (null !== $this->dispatcher) {
            $event = new Event($subject, $marking, $transition);

            $this->dispatcher->dispatch('workflow.enter', $event);
            $this->dispatcher->dispatch(sprintf('workflow.%s.enter', $this->name), $event);
        }

        foreach ($transition->getTos() as $place) {
            $marking->mark($place);

            if (null !== $this->dispatcher) {
                $this->dispatcher->dispatch(sprintf('workflow.%s.enter.%s', $this->name, $place), $event);
            }
        }
    }

    private function announce($subject, Transition $initialTransition, Marking $marking)
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new Event($subject, $marking, $initialTransition);

        foreach ($this->definition->getTransitions() as $transition) {
            if ($this->doCan($subject, $marking, $transition)) {
                $this->dispatcher->dispatch(sprintf('workflow.%s.announce.%s', $this->name, $transition->getName()), $event);
            }
        }
    }
}
