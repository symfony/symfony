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

use Symfony\Component\Workflow\Event\AnnounceEvent;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\LeaveEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Exception\UndefinedTransitionException;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Metadata\MetadataStoreInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        $this->markingStore = $markingStore ?: new MethodMarkingStore();
        $this->dispatcher = $dispatcher;
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getMarking(object $subject)
    {
        $marking = $this->markingStore->getMarking($subject);

        if (!$marking instanceof Marking) {
            throw new LogicException(sprintf('The value returned by the MarkingStore is not an instance of "%s" for workflow "%s".', Marking::class, $this->name));
        }

        // check if the subject is already in the workflow
        if (!$marking->getPlaces()) {
            if (!$this->definition->getInitialPlaces()) {
                throw new LogicException(sprintf('The Marking is empty and there is no initial place for workflow "%s".', $this->name));
            }
            foreach ($this->definition->getInitialPlaces() as $place) {
                $marking->mark($place);
            }

            // update the subject with the new marking
            $this->markingStore->setMarking($subject, $marking);

            $this->entered($subject, null, $marking);
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
    public function can(object $subject, string $transitionName)
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
    public function buildTransitionBlockerList(object $subject, string $transitionName): TransitionBlockerList
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
                return $transitionBlockerList;
            }

            // We prefer to return transitions blocker by something else than
            // marking. Because it means the marking was OK. Transitions are
            // deterministic: it's not possible to have many transitions enabled
            // at the same time that match the same marking with the same name
            if (!$transitionBlockerList->has(TransitionBlocker::BLOCKED_BY_MARKING)) {
                return $transitionBlockerList;
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
    public function apply(object $subject, string $transitionName, array $context = [])
    {
        $marking = $this->getMarking($subject);

        $transitionBlockerList = null;
        $applied = false;
        $approvedTransitionQueue = [];

        foreach ($this->definition->getTransitions() as $transition) {
            if ($transition->getName() !== $transitionName) {
                continue;
            }

            $transitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);
            if (!$transitionBlockerList->isEmpty()) {
                continue;
            }
            $approvedTransitionQueue[] = $transition;
        }

        foreach ($approvedTransitionQueue as $transition) {
            $applied = true;

            $this->leave($subject, $transition, $marking);

            $context = $this->transition($subject, $transition, $marking, $context);

            $this->enter($subject, $transition, $marking);

            $this->markingStore->setMarking($subject, $marking, $context);

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
    public function getEnabledTransitions(object $subject)
    {
        $enabledTransitions = [];
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

    private function buildTransitionBlockerListForTransition(object $subject, Marking $marking, Transition $transition): TransitionBlockerList
    {
        foreach ($transition->getFroms() as $place) {
            if (!$marking->has($place)) {
                return new TransitionBlockerList([
                    TransitionBlocker::createBlockedByMarking($marking),
                ]);
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

    private function guardTransition(object $subject, Marking $marking, Transition $transition): ?GuardEvent
    {
        if (null === $this->dispatcher) {
            return null;
        }

        $event = new GuardEvent($subject, $marking, $transition, $this);

        $this->dispatcher->dispatch($event, WorkflowEvents::GUARD);
        $this->dispatcher->dispatch($event, sprintf('workflow.%s.guard', $this->name));
        $this->dispatcher->dispatch($event, sprintf('workflow.%s.guard.%s', $this->name, $transition->getName()));

        return $event;
    }

    private function leave(object $subject, Transition $transition, Marking $marking): void
    {
        $places = $transition->getFroms();

        if (null !== $this->dispatcher) {
            $event = new LeaveEvent($subject, $marking, $transition, $this);

            $this->dispatcher->dispatch($event, WorkflowEvents::LEAVE);
            $this->dispatcher->dispatch($event, sprintf('workflow.%s.leave', $this->name));

            foreach ($places as $place) {
                $this->dispatcher->dispatch($event, sprintf('workflow.%s.leave.%s', $this->name, $place));
            }
        }

        foreach ($places as $place) {
            $marking->unmark($place);
        }
    }

    private function transition(object $subject, Transition $transition, Marking $marking, array $context): array
    {
        if (null === $this->dispatcher) {
            return $context;
        }

        $event = new TransitionEvent($subject, $marking, $transition, $this);
        $event->setContext($context);

        $this->dispatcher->dispatch($event, WorkflowEvents::TRANSITION);
        $this->dispatcher->dispatch($event, sprintf('workflow.%s.transition', $this->name));
        $this->dispatcher->dispatch($event, sprintf('workflow.%s.transition.%s', $this->name, $transition->getName()));

        return $event->getContext();
    }

    private function enter(object $subject, Transition $transition, Marking $marking): void
    {
        $places = $transition->getTos();

        if (null !== $this->dispatcher) {
            $event = new EnterEvent($subject, $marking, $transition, $this);

            $this->dispatcher->dispatch($event, WorkflowEvents::ENTER);
            $this->dispatcher->dispatch($event, sprintf('workflow.%s.enter', $this->name));

            foreach ($places as $place) {
                $this->dispatcher->dispatch($event, sprintf('workflow.%s.enter.%s', $this->name, $place));
            }
        }

        foreach ($places as $place) {
            $marking->mark($place);
        }
    }

    private function entered(object $subject, ?Transition $transition, Marking $marking): void
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new EnteredEvent($subject, $marking, $transition, $this);

        $this->dispatcher->dispatch($event, WorkflowEvents::ENTERED);
        $this->dispatcher->dispatch($event, sprintf('workflow.%s.entered', $this->name));

        if ($transition) {
            foreach ($transition->getTos() as $place) {
                $this->dispatcher->dispatch($event, sprintf('workflow.%s.entered.%s', $this->name, $place));
            }
        }
    }

    private function completed(object $subject, Transition $transition, Marking $marking): void
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new CompletedEvent($subject, $marking, $transition, $this);

        $this->dispatcher->dispatch($event, WorkflowEvents::COMPLETED);
        $this->dispatcher->dispatch($event, sprintf('workflow.%s.completed', $this->name));
        $this->dispatcher->dispatch($event, sprintf('workflow.%s.completed.%s', $this->name, $transition->getName()));
    }

    private function announce(object $subject, Transition $initialTransition, Marking $marking): void
    {
        if (null === $this->dispatcher) {
            return;
        }

        $event = new AnnounceEvent($subject, $marking, $initialTransition, $this);

        $this->dispatcher->dispatch($event, WorkflowEvents::ANNOUNCE);
        $this->dispatcher->dispatch($event, sprintf('workflow.%s.announce', $this->name));

        foreach ($this->getEnabledTransitions($subject) as $transition) {
            $this->dispatcher->dispatch($event, sprintf('workflow.%s.announce.%s', $this->name, $transition->getName()));
        }
    }
}
