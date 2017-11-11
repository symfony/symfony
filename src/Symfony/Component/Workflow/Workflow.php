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
use Symfony\Component\Workflow\Exception\BlockedTransitionException;
use Symfony\Component\Workflow\Exception\UndefinedTransitionException;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\MultipleStateMarkingStore;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Workflow
{
    private $definition;
    private $markingStore;
    private $dispatcher;
    private $name;

    public function __construct(Definition $definition, MarkingStoreInterface $markingStore = null, EventDispatcherInterface $dispatcher = null, $name = 'unnamed')
    {
        $this->definition = $definition;
        $this->markingStore = $markingStore ?: new MultipleStateMarkingStore();
        $this->dispatcher = $dispatcher;
        $this->name = $name;
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

            // update the subject with the new marking
            $this->markingStore->setMarking($subject, $marking);
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

        return $marking;
    }

    /**
     * Returns true if the transition is enabled.
     *
     * @param object $subject        A subject
     * @param string $transitionName A transition
     *
     * @return bool true if the transition is enabled
     */
    public function can($subject, $transitionName)
    {
        return 0 === count($this->buildTransitionBlockerList($subject, $transitionName));
    }

    /**
     * Returns transition blockers explaining why a transition cannot be made.
     *
     * @param object $subject        A subject
     * @param string $transitionName A transition
     *
     * @return TransitionBlockerList Empty if the transition is possible
     */
    public function buildTransitionBlockerList($subject, string $transitionName): TransitionBlockerList
    {
        $blockerList = $this->getEnabledTransitionsByNameOrTransitionBlockerList($subject, $transitionName);

        // It means there are no blockers, so we return an empty list
        if (!$blockerList instanceof TransitionBlockerList) {
            return new TransitionBlockerList();
        }

        return $blockerList;
    }

    /**
     * Fire a transition.
     *
     * @param object $subject        A subject
     * @param string $transitionName A transition
     *
     * @return Marking The new Marking
     *
     * @throws BlockedTransitionException   If the transition is not applicable
     * @throws UndefinedTransitionException If the transition does not exist
     */
    public function apply($subject, string $transitionName): Marking
    {
        $transitionsOrTransitionBlockerList = $this->getEnabledTransitionsByNameOrTransitionBlockerList(
            $subject,
            $transitionName
        );

        if ($transitionsOrTransitionBlockerList instanceof TransitionBlockerList) {
            $transitionBlockerList = $transitionsOrTransitionBlockerList;

            if ($transitionBlockerList->findByCode(TransitionBlocker::REASON_TRANSITION_NOT_DEFINED)) {
                throw new UndefinedTransitionException(
                    sprintf('Transition "%s" is not defined in workflow "%s".', $transitionName, $this->name)
                );
            }

            throw new BlockedTransitionException(
                sprintf('Unable to apply transition "%s" for workflow "%s".', $transitionName, $this->name),
                $transitionBlockerList
            );
        }

        $transitions = $transitionsOrTransitionBlockerList;

        // We can shortcut the getMarking method in order to boost performance,
        // since the "getEnabledTransitions" method already checks the Marking
        // state
        $marking = $this->markingStore->getMarking($subject);

        foreach ($transitions as $transition) {
            $this->leave($subject, $transition, $marking);

            $this->transition($subject, $transition, $marking);

            $this->enter($subject, $transition, $marking);

            $this->markingStore->setMarking($subject, $marking);

            $this->entered($subject, $transition, $marking);

            $this->completed($subject, $transition, $marking);

            $this->announce($subject, $transition, $marking);
        }

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
            if (0 === count($this->doCan($subject, $marking, $transition))) {
                $enabled[] = $transition;
            }
        }

        return $enabled;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Definition
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * @return MarkingStoreInterface
     */
    public function getMarkingStore()
    {
        return $this->markingStore;
    }

    private function doCan($subject, Marking $marking, Transition $transition): TransitionBlockerList
    {
        foreach ($transition->getFroms() as $place) {
            if (!$marking->has($place)) {
                return new TransitionBlockerList(array(TransitionBlocker::createNotApplicable($transition->getName())));
            }
        }

        return $this->guardTransition($subject, $marking, $transition);
    }

    private function guardTransition($subject, Marking $marking, Transition $transition): TransitionBlockerList
    {
        if (null === $this->dispatcher) {
            return new TransitionBlockerList();
        }

        $event = new GuardEvent($subject, $marking, $transition, $this->name);

        $this->dispatcher->dispatch('workflow.guard', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.guard', $this->name), $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.guard.%s', $this->name, $transition->getName()), $event);

        return $event->getTransitionBlockerList();
    }

    private function leave($subject, Transition $transition, Marking $marking)
    {
        $places = $transition->getFroms();

        if (null !== $this->dispatcher) {
            $event = new Event($subject, $marking, $transition, $this->name);

            $this->dispatcher->dispatch('workflow.leave', $event);
            $this->dispatcher->dispatch(sprintf('workflow.%s.leave', $this->name), $event);

            foreach ($places as $place) {
                $this->dispatcher->dispatch(sprintf('workflow.%s.leave.%s', $this->name, $place), $event);
            }
        }

        foreach ($places as $place) {
            $marking->unmark($place);
        }
    }

    private function transition($subject, Transition $transition, Marking $marking)
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new Event($subject, $marking, $transition, $this->name);

        $this->dispatcher->dispatch('workflow.transition', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.transition', $this->name), $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.transition.%s', $this->name, $transition->getName()), $event);
    }

    private function enter($subject, Transition $transition, Marking $marking)
    {
        $places = $transition->getTos();

        if (null !== $this->dispatcher) {
            $event = new Event($subject, $marking, $transition, $this->name);

            $this->dispatcher->dispatch('workflow.enter', $event);
            $this->dispatcher->dispatch(sprintf('workflow.%s.enter', $this->name), $event);

            foreach ($places as $place) {
                $this->dispatcher->dispatch(sprintf('workflow.%s.enter.%s', $this->name, $place), $event);
            }
        }

        foreach ($places as $place) {
            $marking->mark($place);
        }
    }

    private function entered($subject, Transition $transition, Marking $marking)
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new Event($subject, $marking, $transition, $this->name);

        $this->dispatcher->dispatch('workflow.entered', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.entered', $this->name), $event);

        foreach ($transition->getTos() as $place) {
            $this->dispatcher->dispatch(sprintf('workflow.%s.entered.%s', $this->name, $place), $event);
        }
    }

    private function completed($subject, Transition $transition, Marking $marking)
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new Event($subject, $marking, $transition, $this->name);

        $this->dispatcher->dispatch('workflow.completed', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.completed', $this->name), $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.completed.%s', $this->name, $transition->getName()), $event);
    }

    private function announce($subject, Transition $initialTransition, Marking $marking)
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new Event($subject, $marking, $initialTransition, $this->name);

        $this->dispatcher->dispatch('workflow.announce', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.announce', $this->name), $event);

        foreach ($this->getEnabledTransitions($subject) as $transition) {
            $this->dispatcher->dispatch(sprintf('workflow.%s.announce.%s', $this->name, $transition->getName()), $event);
        }
    }

    /**
     * @param string $transitionName
     *
     * @return Transition[]
     */
    private function getTransitionsByName(string $transitionName): array
    {
        return array_filter(
            $this->definition->getTransitions(),
            function (Transition $transition) use ($transitionName) {
                return $transition->getName() === $transitionName;
            }
        );
    }

    /**
     * Returns all enabled transitions or the most specific transition blocker list.
     *
     * @param object $subject        A subject
     * @param string $transitionName
     *
     * @return Transition[]|TransitionBlockerList All enabled transitions or a blocker list
     *                                            if no enabled transitions can be found
     */
    private function getEnabledTransitionsByNameOrTransitionBlockerList($subject, string $transitionName)
    {
        $eligibleTransitions = $this->getTransitionsByName($transitionName);

        if (!$eligibleTransitions) {
            return new TransitionBlockerList(array(TransitionBlocker::createNotDefined($transitionName, $this->name)));
        }

        $marking = $this->getMarking($subject);
        $transitions = array();

        /** @var TransitionBlockerList[] $transitionBlockerLists */
        $transitionBlockerLists = [];

        foreach ($eligibleTransitions as $transition) {
            $transitionBlockerLists[]
                = $transitionBlockerList
                = $this->doCan($subject, $marking, $transition);

            if (0 === count($transitionBlockerList)) {
                $transitions[] = $transition;
            }
        }

        if ($transitions) {
            return $transitions;
        }

        // Try to find the most specific blocker list, by ignoring the ones
        // that say, that the transition is impossible, because it's not applicable.
        // If such a list is not found, then apparently the transition is really
        // not applicable. All this makes more sense when there are many transitions
        // with the same name. In case every transition has a unique name, only one
        // blocker list is retrieved, which is exactly the behaviour we're trying
        // to reproduce in the case when there are many transitions with the same
        // name.
        //
        // Also, at this point we are guaranteed to have at least 1 blocker list.
        foreach ($transitionBlockerLists as $transitionBlockerList) {
            if (!$transitionBlockerList->findByCode(TransitionBlocker::REASON_TRANSITION_NOT_APPLICABLE)) {
                return $transitionBlockerList;
            }
        }

        return $transitionBlockerLists[0];
    }
}
