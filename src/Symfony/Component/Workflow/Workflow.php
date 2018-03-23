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
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Exception\UndefinedTransitionException;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\MultipleStateMarkingStore;
use Symfony\Component\Workflow\Metadata\MetadataStoreInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Workflow implements WorkflowInterface
{
    private $definition;
    private $markingStore;
    private $dispatcher;
    private $name;

    public function __construct(Definition $definition, MarkingStoreInterface $markingStore = null, EventDispatcherInterface $dispatcher = null, string $name = 'unnamed')
    {
        $this->definition = $definition;
        $this->markingStore = $markingStore ?: new MultipleStateMarkingStore();
        $this->dispatcher = $dispatcher;
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function can($subject, $transitionName)
    {
        $transitions = $this->definition->getTransitions();
        $marking = $this->getMarking($subject);

        foreach ($transitions as $transition) {
            if ($transition->getName() !== $transitionName) {
                continue;
            }

            $transitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);

            if ($transitionBlockerList->isEmpty()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function buildTransitionBlockerList($subject, string $transitionName): TransitionBlockerList
    {
        $transitions = $this->definition->getTransitions();
        $marking = $this->getMarking($subject);
        $transitionBlockerList = null;

        foreach ($transitions as $transition) {
            if ($transition->getName() !== $transitionName) {
                continue;
            }

            $transitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);

            if ($transitionBlockerList->isEmpty()) {
                continue;
            }
        }

        if (!$transitionBlockerList) {
            throw new UndefinedTransitionException($subject, $transitionName, $this);
        }

        return $transitionBlockerList;
    }

    /**
     * {@inheritdoc}
     */
    public function apply($subject, $transitionName)
    {
        $marking = $this->getMarking($subject);

        $transitionBlockerList = null;
        $applied = false;

        foreach ($this->definition->getTransitions() as $transition) {
            if ($transition->getName() !== $transitionName) {
                continue;
            }

            $transitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);
            if (!$transitionBlockerList->isEmpty()) {
                continue;
            }

            $applied = true;

            $this->leave($subject, $transition, $marking);

            $this->transition($subject, $transition, $marking);

            $this->enter($subject, $transition, $marking);

            $this->markingStore->setMarking($subject, $marking);

            $this->entered($subject, $transition, $marking);

            $this->completed($subject, $transition, $marking);

            $this->announce($subject, $transition, $marking);
        }

        if (!$transitionBlockerList) {
            throw new UndefinedTransitionException($subject, $transitionName, $this);
        }

        if (!$applied) {
            throw new NotEnabledTransitionException($subject, $transitionName, $this, $transitionBlockerList);
        }

        return $marking;
    }

    /**
     * {@inheritdoc}
     */
    public function getEnabledTransitions($subject)
    {
        $enabledTransitions = array();
        $marking = $this->getMarking($subject);

        foreach ($this->definition->getTransitions() as $transition) {
            $transitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);
            if ($transitionBlockerList->isEmpty()) {
                $enabledTransitions[] = $transition;
            }
        }

        return $enabledTransitions;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * {@inheritdoc}
     */
    public function getMarkingStore()
    {
        return $this->markingStore;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataStore(): MetadataStoreInterface
    {
        return $this->definition->getMetadataStore();
    }

    private function buildTransitionBlockerListForTransition($subject, Marking $marking, Transition $transition)
    {
        foreach ($transition->getFroms() as $place) {
            if (!$marking->has($place)) {
                return new TransitionBlockerList(array(
                    TransitionBlocker::createBlockedByMarking($marking),
                ));
            }
        }

        if (null === $this->dispatcher) {
            return new TransitionBlockerList();
        }

        $event = $this->guardTransition($subject, $marking, $transition);

        if ($event->isBlocked()) {
            return $event->getTransitionBlockerList();
        }

        return new TransitionBlockerList();
    }

    private function guardTransition($subject, Marking $marking, Transition $transition): ?GuardEvent
    {
        if (null === $this->dispatcher) {
            return null;
        }

        $event = new GuardEvent($subject, $marking, $transition, $this);

        $this->dispatcher->dispatch('workflow.guard', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.guard', $this->name), $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.guard.%s', $this->name, $transition->getName()), $event);

        return $event;
    }

    private function leave($subject, Transition $transition, Marking $marking): void
    {
        $places = $transition->getFroms();

        if (null !== $this->dispatcher) {
            $event = new Event($subject, $marking, $transition, $this);

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

    private function transition($subject, Transition $transition, Marking $marking): void
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new Event($subject, $marking, $transition, $this);

        $this->dispatcher->dispatch('workflow.transition', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.transition', $this->name), $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.transition.%s', $this->name, $transition->getName()), $event);
    }

    private function enter($subject, Transition $transition, Marking $marking): void
    {
        $places = $transition->getTos();

        if (null !== $this->dispatcher) {
            $event = new Event($subject, $marking, $transition, $this);

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

    private function entered($subject, Transition $transition, Marking $marking): void
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new Event($subject, $marking, $transition, $this);

        $this->dispatcher->dispatch('workflow.entered', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.entered', $this->name), $event);

        foreach ($transition->getTos() as $place) {
            $this->dispatcher->dispatch(sprintf('workflow.%s.entered.%s', $this->name, $place), $event);
        }
    }

    private function completed($subject, Transition $transition, Marking $marking): void
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new Event($subject, $marking, $transition, $this);

        $this->dispatcher->dispatch('workflow.completed', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.completed', $this->name), $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.completed.%s', $this->name, $transition->getName()), $event);
    }

    private function announce($subject, Transition $initialTransition, Marking $marking): void
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new Event($subject, $marking, $initialTransition, $this);

        $this->dispatcher->dispatch('workflow.announce', $event);
        $this->dispatcher->dispatch(sprintf('workflow.%s.announce', $this->name), $event);

        foreach ($this->getEnabledTransitions($subject) as $transition) {
            $this->dispatcher->dispatch(sprintf('workflow.%s.announce.%s', $this->name, $transition->getName()), $event);
        }
    }
}
