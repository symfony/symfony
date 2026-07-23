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
use Symfony\Component\Workflow\Exception\InvalidArgumentException;
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
 * @author Carlos Pereira De Amorim <carlos@shauri.fr>
 */
class Workflow implements WorkflowInterface
{
    public const DISABLE_LEAVE_EVENT = 'workflow_disable_leave_event';
    public const DISABLE_TRANSITION_EVENT = 'workflow_disable_transition_event';
    public const DISABLE_ENTER_EVENT = 'workflow_disable_enter_event';
    public const DISABLE_ENTERED_EVENT = 'workflow_disable_entered_event';
    public const DISABLE_COMPLETED_EVENT = 'workflow_disable_completed_event';
    public const DISABLE_ANNOUNCE_EVENT = 'workflow_disable_announce_event';

    public const DEFAULT_INITIAL_CONTEXT = ['initial' => true];

    private const DISABLE_EVENTS_MAPPING = [
        WorkflowEvents::LEAVE => self::DISABLE_LEAVE_EVENT,
        WorkflowEvents::TRANSITION => self::DISABLE_TRANSITION_EVENT,
        WorkflowEvents::ENTER => self::DISABLE_ENTER_EVENT,
        WorkflowEvents::ENTERED => self::DISABLE_ENTERED_EVENT,
        WorkflowEvents::COMPLETED => self::DISABLE_COMPLETED_EVENT,
        WorkflowEvents::ANNOUNCE => self::DISABLE_ANNOUNCE_EVENT,
    ];

    private const DISPATCHABLE_EVENTS = [
        WorkflowEvents::LEAVE,
        WorkflowEvents::TRANSITION,
        WorkflowEvents::ENTER,
        WorkflowEvents::ENTERED,
        WorkflowEvents::COMPLETED,
        WorkflowEvents::ANNOUNCE,
    ];

    private MarkingStoreInterface $markingStore;

    /**
     * @param string[]|null $eventsToDispatch Controls which {@see WorkflowEvents} are dispatched:
     *                                        - `null` (default): fire all events.
     *                                        - `[]`: fire no event (except the {@see GuardEvent}).
     *                                        - allow-list, e.g. `['workflow.transition', 'workflow.enter']`: fire only the listed
     *                                        events plus the {@see GuardEvent}.
     *                                        - block-list, e.g. `['!workflow.announce']`: fire every event except the listed ones;
     *                                        future {@see WorkflowEvents} are dispatched by default. The {@see GuardEvent} can
     *                                        never be suppressed; blocking it with `!workflow.guard` throws an
     *                                        {@see InvalidArgumentException}.
     *                                        Mixing allow-list and block-list entries in the same array is not supported and throws
     *                                        an {@see InvalidArgumentException}.
     */
    public function __construct(
        private Definition $definition,
        ?MarkingStoreInterface $markingStore = null,
        private ?EventDispatcherInterface $dispatcher = null,
        private string $name = 'unnamed',
        private ?array $eventsToDispatch = null,
    ) {
        $this->markingStore = $markingStore ?? new MethodMarkingStore();

        if (null !== $this->eventsToDispatch && [] !== $this->eventsToDispatch) {
            $hasAllowList = false;
            $hasBlockList = false;
            foreach ($this->eventsToDispatch as $entry) {
                if (str_starts_with($entry, '!')) {
                    $hasBlockList = true;
                } else {
                    $hasAllowList = true;
                }
            }
            if ($hasAllowList && $hasBlockList) {
                throw new InvalidArgumentException(\sprintf('Cannot mix allow-list and block-list entries in $eventsToDispatch for workflow "%s": every entry must start with "!" (block-list mode) or none of them must (allow-list mode).', $name));
            }

            if ($hasBlockList) {
                $blockedEvents = [];
                foreach ($this->eventsToDispatch as $entry) {
                    $eventName = substr($entry, 1);
                    if (WorkflowEvents::GUARD === $eventName) {
                        throw new InvalidArgumentException(\sprintf('The "%s" event cannot be disabled in $eventsToDispatch for workflow "%s": it is always dispatched.', WorkflowEvents::GUARD, $name));
                    }
                    $blockedEvents[] = $eventName;
                }
                $this->eventsToDispatch = array_values(array_diff(self::DISPATCHABLE_EVENTS, $blockedEvents));
            }
        }
    }

    public function getMarking(object $subject, array $context = []): Marking
    {
        $marking = $this->markingStore->getMarking($subject);

        // check if the subject is already in the workflow
        if (!$marking->getPlaces()) {
            if (!$this->definition->getInitialPlaces()) {
                throw new LogicException(\sprintf('The Marking is empty and there is no initial place for workflow "%s".', $this->name));
            }
            foreach ($this->definition->getInitialPlaces() as $place) {
                $marking->mark($place);
            }

            // update the subject with the new marking
            $this->markingStore->setMarking($subject, $marking);

            if (!$context) {
                $context = self::DEFAULT_INITIAL_CONTEXT;
            }

            $this->entered($subject, null, $marking, $context);
        }

        // check that the subject has a known place
        $places = $this->definition->getPlaces();
        foreach ($marking->getPlaces() as $placeName => $nbToken) {
            if (!isset($places[$placeName])) {
                $message = \sprintf('Place "%s" is not valid for workflow "%s".', $placeName, $this->name);
                if (!$places) {
                    $message .= ' It seems you forgot to add places to the current workflow.';
                }

                throw new LogicException($message);
            }
        }

        return $marking;
    }

    public function can(object $subject, string $transitionName): bool
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

    public function apply(object $subject, string $transitionName, array $context = []): Marking
    {
        $marking = $this->getMarking($subject, $context);

        $transitionExist = false;
        $approvedTransitions = [];
        $bestTransitionBlockerList = null;

        foreach ($this->definition->getTransitions() as $transition) {
            if ($transition->getName() !== $transitionName) {
                continue;
            }

            $transitionExist = true;

            $tmpTransitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);

            if ($tmpTransitionBlockerList->isEmpty()) {
                $approvedTransitions[] = $transition;
                continue;
            }

            if (!$bestTransitionBlockerList) {
                $bestTransitionBlockerList = $tmpTransitionBlockerList;
                continue;
            }

            // We prefer to return transitions blocker by something else than
            // marking. Because it means the marking was OK. Transitions are
            // deterministic: it's not possible to have many transitions enabled
            // at the same time that match the same marking with the same name
            if (!$tmpTransitionBlockerList->has(TransitionBlocker::BLOCKED_BY_MARKING)) {
                $bestTransitionBlockerList = $tmpTransitionBlockerList;
            }
        }

        if (!$transitionExist) {
            throw new UndefinedTransitionException($subject, $transitionName, $this, $context);
        }

        if (!$approvedTransitions) {
            throw new NotEnabledTransitionException($subject, $transitionName, $this, $bestTransitionBlockerList, $context);
        }

        foreach ($approvedTransitions as $transition) {
            $this->leave($subject, $transition, $marking, $context);

            $context = $this->transition($subject, $transition, $marking, $context);

            $this->enter($subject, $transition, $marking, $context);

            $this->markingStore->setMarking($subject, $marking, $context);

            $this->entered($subject, $transition, $marking, $context);

            $this->completed($subject, $transition, $marking, $context);

            $this->announce($subject, $transition, $marking, $context);
        }

        $marking->setContext($context);

        return $marking;
    }

    public function getEnabledTransitions(object $subject): array
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

    public function getEnabledTransition(object $subject, string $name): ?Transition
    {
        $marking = $this->getMarking($subject);

        foreach ($this->definition->getTransitions() as $transition) {
            if ($transition->getName() !== $name) {
                continue;
            }
            $transitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);
            if (!$transitionBlockerList->isEmpty()) {
                continue;
            }

            return $transition;
        }

        return null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefinition(): Definition
    {
        return $this->definition;
    }

    public function getMarkingStore(): MarkingStoreInterface
    {
        return $this->markingStore;
    }

    public function getMetadataStore(): MetadataStoreInterface
    {
        return $this->definition->getMetadataStore();
    }

    private function buildTransitionBlockerListForTransition(object $subject, Marking $marking, Transition $transition): TransitionBlockerList
    {
        foreach ($transition->getFroms(true) as $arc) {
            if ($marking->getTokenCount($arc->place) < $arc->weight) {
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
        $this->dispatcher->dispatch($event, \sprintf('workflow.%s.guard', $this->name));
        $this->dispatcher->dispatch($event, \sprintf('workflow.%s.guard.%s', $this->name, $transition->getName()));

        return $event;
    }

    private function leave(object $subject, Transition $transition, Marking $marking, array $context = []): void
    {
        $arcs = $transition->getFroms(true);

        if ($this->shouldDispatchEvent(WorkflowEvents::LEAVE, $context)) {
            $event = new LeaveEvent($subject, $marking, $transition, $this, $context);

            $this->dispatcher->dispatch($event, WorkflowEvents::LEAVE);
            $this->dispatcher->dispatch($event, \sprintf('workflow.%s.leave', $this->name));

            foreach ($arcs as $arc) {
                $this->dispatcher->dispatch($event, \sprintf('workflow.%s.leave.%s', $this->name, $arc->place));
            }
        }

        foreach ($arcs as $arc) {
            $marking->unmark($arc->place, $arc->weight);
        }
    }

    private function transition(object $subject, Transition $transition, Marking $marking, array $context): array
    {
        if (!$this->shouldDispatchEvent(WorkflowEvents::TRANSITION, $context)) {
            return $context;
        }

        $event = new TransitionEvent($subject, $marking, $transition, $this, $context);

        $this->dispatcher->dispatch($event, WorkflowEvents::TRANSITION);
        $this->dispatcher->dispatch($event, \sprintf('workflow.%s.transition', $this->name));
        $this->dispatcher->dispatch($event, \sprintf('workflow.%s.transition.%s', $this->name, $transition->getName()));

        return $event->getContext();
    }

    private function enter(object $subject, Transition $transition, Marking $marking, array $context): void
    {
        $arcs = $transition->getTos(true);

        if ($this->shouldDispatchEvent(WorkflowEvents::ENTER, $context)) {
            $event = new EnterEvent($subject, $marking, $transition, $this, $context);

            $this->dispatcher->dispatch($event, WorkflowEvents::ENTER);
            $this->dispatcher->dispatch($event, \sprintf('workflow.%s.enter', $this->name));

            foreach ($arcs as $arc) {
                $this->dispatcher->dispatch($event, \sprintf('workflow.%s.enter.%s', $this->name, $arc->place));
            }
        }

        foreach ($arcs as $arc) {
            $marking->mark($arc->place, $arc->weight);
        }
    }

    private function entered(object $subject, ?Transition $transition, Marking $marking, array $context): void
    {
        if (!$this->shouldDispatchEvent(WorkflowEvents::ENTERED, $context)) {
            return;
        }

        $event = new EnteredEvent($subject, $marking, $transition, $this, $context);

        $this->dispatcher->dispatch($event, WorkflowEvents::ENTERED);
        $this->dispatcher->dispatch($event, \sprintf('workflow.%s.entered', $this->name));

        $placeNames = [];
        if ($transition) {
            $placeNames = array_column($transition->getTos(true), 'place');
        } elseif ($this->definition->getInitialPlaces()) {
            $placeNames = $this->definition->getInitialPlaces();
        }
        foreach ($placeNames as $placeName) {
            $this->dispatcher->dispatch($event, \sprintf('workflow.%s.entered.%s', $this->name, $placeName));
        }
    }

    private function completed(object $subject, Transition $transition, Marking $marking, array $context): void
    {
        if (!$this->shouldDispatchEvent(WorkflowEvents::COMPLETED, $context)) {
            return;
        }

        $event = new CompletedEvent($subject, $marking, $transition, $this, $context);

        $this->dispatcher->dispatch($event, WorkflowEvents::COMPLETED);
        $this->dispatcher->dispatch($event, \sprintf('workflow.%s.completed', $this->name));
        $this->dispatcher->dispatch($event, \sprintf('workflow.%s.completed.%s', $this->name, $transition->getName()));
    }

    private function announce(object $subject, Transition $initialTransition, Marking $marking, array $context): void
    {
        if (!$this->shouldDispatchEvent(WorkflowEvents::ANNOUNCE, $context)) {
            return;
        }

        $event = new AnnounceEvent($subject, $marking, $initialTransition, $this, $context);

        $this->dispatcher->dispatch($event, WorkflowEvents::ANNOUNCE);
        $this->dispatcher->dispatch($event, \sprintf('workflow.%s.announce', $this->name));

        foreach ($this->getEnabledTransitions($subject) as $transition) {
            $this->dispatcher->dispatch($event, \sprintf('workflow.%s.announce.%s', $this->name, $transition->getName()));
        }
    }

    private function shouldDispatchEvent(string $eventName, array $context): bool
    {
        if (null === $this->dispatcher) {
            return false;
        }

        if ($context[self::DISABLE_EVENTS_MAPPING[$eventName]] ?? false) {
            return false;
        }

        if (null === $this->eventsToDispatch) {
            return true;
        }

        if ([] === $this->eventsToDispatch) {
            return false;
        }

        return \in_array($eventName, $this->eventsToDispatch, true);
    }
}
