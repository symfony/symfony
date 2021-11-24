<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Exception\UndefinedTransitionException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Tests\fixtures\AlphabeticalEnum;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\WorkflowEvents;

class WorkflowTest extends TestCase
{
    use WorkflowBuilderTrait;

    public function testGetMarkingWithEmptyDefinition()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The Marking is empty and there is no initial place for workflow "unnamed".');
        $subject = new Subject();
        $workflow = new Workflow(new Definition([], []), new MethodMarkingStore());

        $workflow->getMarking($subject);
    }

    public function testGetMarkingWithImpossiblePlace()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Place "nope" is not valid for workflow "unnamed".');
        $subject = new Subject();
        $subject->setMarking(['nope' => 1]);
        $workflow = new Workflow(new Definition([], []), new MethodMarkingStore());

        $workflow->getMarking($subject);
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testGetMarkingWithEmptyInitialMarking(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $marking = $workflow->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertTrue($marking->has($this->getTypedPlaceValue('a', $useEnumerations)));
        $this->assertSame($useEnumerations ? [AlphabeticalEnum::A] : ['a' => 1], $subject->getMarking());
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testGetMarkingWithExistingMarking(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::B, AlphabeticalEnum::C] : ['b' => 1, 'c' => 1]);

        $workflow = new Workflow($definition, new MethodMarkingStore());

        $marking = $workflow->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);

        $this->assertTrue($marking->has($this->getTypedPlaceValue('b', $useEnumerations)));
        $this->assertTrue($marking->has($this->getTypedPlaceValue('c', $useEnumerations)));
    }

    public function testCanWithUnexistingTransition()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new Subject();
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $this->assertFalse($workflow->can($subject, 'foobar'));
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testCan(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $this->assertTrue($workflow->can($subject, 't1'));
        $this->assertFalse($workflow->can($subject, 't2'));

        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::B] : ['b' => 1]);

        $this->assertFalse($workflow->can($subject, 't1'));
        // In a workflow net, all "from" places should contain a token to enable
        // the transition.
        $this->assertFalse($workflow->can($subject, 't2'));

        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::B, AlphabeticalEnum::C] : ['b' => 1, 'c' => 1]);

        $this->assertFalse($workflow->can($subject, 't1'));
        $this->assertTrue($workflow->can($subject, 't2'));

        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::F] : ['f' => 1]);

        $this->assertFalse($workflow->can($subject, 't5'));
        $this->assertTrue($workflow->can($subject, 't6'));
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testCanWithGuard(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener('workflow.workflow_name.guard.t1', function (GuardEvent $event) {
            $event->setBlocked(true);
        });
        $workflow = new Workflow($definition, new MethodMarkingStore(), $eventDispatcher, 'workflow_name');

        $this->assertFalse($workflow->can($subject, 't1'));
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testCanDoesNotTriggerGuardEventsForNotEnabledTransitions(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();

        $dispatchedEvents = [];
        $eventDispatcher = new EventDispatcher();

        $workflow = new Workflow($definition, new MethodMarkingStore(), $eventDispatcher, 'workflow_name');
        $workflow->apply($subject, 't1');
        $workflow->apply($subject, 't2');

        $eventDispatcher->addListener('workflow.workflow_name.guard.t3', function () use (&$dispatchedEvents) {
            $dispatchedEvents[] = 'workflow_name.guard.t3';
        });
        $eventDispatcher->addListener('workflow.workflow_name.guard.t4', function () use (&$dispatchedEvents) {
            $dispatchedEvents[] = 'workflow_name.guard.t4';
        });

        $workflow->can($subject, 't3');

        $this->assertSame(['workflow_name.guard.t3'], $dispatchedEvents);
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testCanWithSameNameTransition(bool $useEnumerations)
    {
        $definition = $this->createWorkflowWithSameNameTransition($useEnumerations);
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $subject = new Subject();
        $this->assertTrue($workflow->can($subject, 'a_to_bc'));
        $this->assertFalse($workflow->can($subject, 'b_to_c'));
        $this->assertFalse($workflow->can($subject, 'to_a'));

        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::B] : ['b' => 1]);
        $this->assertFalse($workflow->can($subject, 'a_to_bc'));
        $this->assertTrue($workflow->can($subject, 'b_to_c'));
        $this->assertTrue($workflow->can($subject, 'to_a'));
    }

    public function testBuildTransitionBlockerListReturnsUndefinedTransition()
    {
        $this->expectException(UndefinedTransitionException::class);
        $this->expectExceptionMessage('Transition "404 Not Found" is not defined for workflow "unnamed".');
        $definition = $this->createSimpleWorkflowDefinition();
        $subject = new Subject();
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $workflow->buildTransitionBlockerList($subject, '404 Not Found');
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testBuildTransitionBlockerList(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $this->assertTrue($workflow->buildTransitionBlockerList($subject, 't1')->isEmpty());
        $this->assertFalse($workflow->buildTransitionBlockerList($subject, 't2')->isEmpty());

        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::B] : ['b' => 1]);

        $this->assertFalse($workflow->buildTransitionBlockerList($subject, 't1')->isEmpty());
        $this->assertFalse($workflow->buildTransitionBlockerList($subject, 't2')->isEmpty());

        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::B, AlphabeticalEnum::C] : ['b' => 1, 'c' => 1]);

        $this->assertFalse($workflow->buildTransitionBlockerList($subject, 't1')->isEmpty());
        $this->assertTrue($workflow->buildTransitionBlockerList($subject, 't2')->isEmpty());

        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::F] : ['f' => 1]);

        $this->assertFalse($workflow->buildTransitionBlockerList($subject, 't5')->isEmpty());
        $this->assertTrue($workflow->buildTransitionBlockerList($subject, 't6')->isEmpty());
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testBuildTransitionBlockerListReturnsReasonsProvidedByMarking(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $transitionBlockerList = $workflow->buildTransitionBlockerList($subject, 't2');
        $this->assertCount(1, $transitionBlockerList);
        $blockers = iterator_to_array($transitionBlockerList);
        $this->assertSame('The marking does not enable the transition.', $blockers[0]->getMessage());
        $this->assertSame('19beefc8-6b1e-4716-9d07-a39bd6d16e34', $blockers[0]->getCode());
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testBuildTransitionBlockerListReturnsReasonsProvidedInGuards(bool $useEnumerations)
    {
        $definition = $this->createSimpleWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $dispatcher = new EventDispatcher();
        $workflow = new Workflow($definition, new MethodMarkingStore(), $dispatcher);

        $dispatcher->addListener('workflow.guard', function (GuardEvent $event) {
            $event->addTransitionBlocker(new TransitionBlocker('Transition blocker 1', 'blocker_1'));
            $event->addTransitionBlocker(new TransitionBlocker('Transition blocker 2', 'blocker_2'));
        });
        $dispatcher->addListener('workflow.guard', function (GuardEvent $event) {
            $event->addTransitionBlocker(new TransitionBlocker('Transition blocker 3', 'blocker_3'));
        });
        $dispatcher->addListener('workflow.guard', function (GuardEvent $event) {
            $event->setBlocked(true);
        });
        $dispatcher->addListener('workflow.guard', function (GuardEvent $event) {
            $event->setBlocked(true, 'You should not pass !!');
        });

        $transitionBlockerList = $workflow->buildTransitionBlockerList($subject, 't1');
        $this->assertCount(5, $transitionBlockerList);
        $blockers = iterator_to_array($transitionBlockerList);
        $this->assertSame('Transition blocker 1', $blockers[0]->getMessage());
        $this->assertSame('blocker_1', $blockers[0]->getCode());
        $this->assertSame('Transition blocker 2', $blockers[1]->getMessage());
        $this->assertSame('blocker_2', $blockers[1]->getCode());
        $this->assertSame('Transition blocker 3', $blockers[2]->getMessage());
        $this->assertSame('blocker_3', $blockers[2]->getCode());
        $this->assertSame('The transition has been blocked by a guard (Symfony\Component\Workflow\Tests\WorkflowTest).', $blockers[3]->getMessage());
        $this->assertSame('e8b5bbb9-5913-4b98-bfa6-65dbd228a82a', $blockers[3]->getCode());
        $this->assertSame('You should not pass !!', $blockers[4]->getMessage());
        $this->assertSame('e8b5bbb9-5913-4b98-bfa6-65dbd228a82a', $blockers[4]->getCode());
    }

    public function testApplyWithNotExisingTransition()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new Subject();
        $workflow = new Workflow($definition, new MethodMarkingStore());
        $context = [
            'lorem' => 'ipsum',
        ];

        try {
            $workflow->apply($subject, '404 Not Found', $context);

            $this->fail('Should throw an exception');
        } catch (UndefinedTransitionException $e) {
            $this->assertSame('Transition "404 Not Found" is not defined for workflow "unnamed".', $e->getMessage());
            $this->assertSame($e->getContext(), $context);
        }
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testApplyWithNotEnabledTransition(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $workflow = new Workflow($definition, new MethodMarkingStore());
        $context = [
            'lorem' => 'ipsum',
        ];

        try {
            $workflow->apply($subject, 't2', $context);

            $this->fail('Should throw an exception');
        } catch (NotEnabledTransitionException $e) {
            $this->assertSame('Transition "t2" is not enabled for workflow "unnamed".', $e->getMessage());
            $this->assertCount(1, $e->getTransitionBlockerList());
            $list = iterator_to_array($e->getTransitionBlockerList());
            $this->assertSame('The marking does not enable the transition.', $list[0]->getMessage());
            $this->assertSame($e->getWorkflow(), $workflow);
            $this->assertSame($e->getSubject(), $subject);
            $this->assertSame($e->getTransitionName(), 't2');
            $this->assertSame($e->getContext(), $context);
        }
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testApply(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $marking = $workflow->apply($subject, 't1');

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertFalse($marking->has($this->getTypedPlaceValue('a', $useEnumerations)));
        $this->assertTrue($marking->has($this->getTypedPlaceValue('b', $useEnumerations)));
        $this->assertTrue($marking->has($this->getTypedPlaceValue('c', $useEnumerations)));
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testApplyWithSameNameTransition(bool $useEnumerations)
    {
        $subject = new Subject();
        $definition = $this->createWorkflowWithSameNameTransition($useEnumerations);
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $marking = $workflow->apply($subject, 'a_to_bc');

        $this->assertFalse($marking->has($this->getTypedPlaceValue('a', $useEnumerations)));
        $this->assertTrue($marking->has($this->getTypedPlaceValue('b', $useEnumerations)));
        $this->assertTrue($marking->has($this->getTypedPlaceValue('c', $useEnumerations)));

        $marking = $workflow->apply($subject, 'to_a');

        $this->assertTrue($marking->has($this->getTypedPlaceValue('a', $useEnumerations)));
        $this->assertFalse($marking->has($this->getTypedPlaceValue('b', $useEnumerations)));
        $this->assertFalse($marking->has($this->getTypedPlaceValue('c', $useEnumerations)));

        $workflow->apply($subject, 'a_to_bc');
        $marking = $workflow->apply($subject, 'b_to_c');

        $this->assertFalse($marking->has($this->getTypedPlaceValue('a', $useEnumerations)));
        $this->assertFalse($marking->has($this->getTypedPlaceValue('b', $useEnumerations)));
        $this->assertTrue($marking->has($this->getTypedPlaceValue('c', $useEnumerations)));

        $marking = $workflow->apply($subject, 'to_a');

        $this->assertTrue($marking->has($this->getTypedPlaceValue('a', $useEnumerations)));
        $this->assertFalse($marking->has($this->getTypedPlaceValue('b', $useEnumerations)));
        $this->assertFalse($marking->has($this->getTypedPlaceValue('c', $useEnumerations)));
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testApplyWithSameNameTransition2(bool $useEnumerations)
    {
        $subject = new Subject();
        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::A, AlphabeticalEnum::B] : ['a' => 1, 'b' => 1]);

        $places = $useEnumerations ? [
            AlphabeticalEnum::A,
            AlphabeticalEnum::B,
            AlphabeticalEnum::C,
            AlphabeticalEnum::D,
        ] : range('a', 'd');
        $transitions = [];
        $transitions[] = new Transition('t', $this->getTypedPlaceValue('a', $useEnumerations), $this->getTypedPlaceValue('c', $useEnumerations));
        $transitions[] = new Transition('t', $this->getTypedPlaceValue('b', $useEnumerations), $this->getTypedPlaceValue('d', $useEnumerations));
        $definition = new Definition($places, $transitions);
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $marking = $workflow->apply($subject, 't');

        $this->assertFalse($marking->has($this->getTypedPlaceValue('a', $useEnumerations)));
        $this->assertFalse($marking->has($this->getTypedPlaceValue('b', $useEnumerations)));
        $this->assertTrue($marking->has($this->getTypedPlaceValue('c', $useEnumerations)));
        $this->assertTrue($marking->has($this->getTypedPlaceValue('d', $useEnumerations)));
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testApplyWithSameNameTransition3(bool $useEnumerations)
    {
        $subject = new Subject();
        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::A] : ['a' => 1]);

        $places = $useEnumerations ? [
            AlphabeticalEnum::A,
            AlphabeticalEnum::B,
            AlphabeticalEnum::C,
            AlphabeticalEnum::D,
        ] : range('a', 'd');
        $transitions = [];
        $transitions[] = new Transition('t', $this->getTypedPlaceValue('a', $useEnumerations), $this->getTypedPlaceValue('b', $useEnumerations));
        $transitions[] = new Transition('t', $this->getTypedPlaceValue('b', $useEnumerations), $this->getTypedPlaceValue('c', $useEnumerations));
        $transitions[] = new Transition('t', $this->getTypedPlaceValue('c', $useEnumerations), $this->getTypedPlaceValue('d', $useEnumerations));
        $definition = new Definition($places, $transitions);
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $marking = $workflow->apply($subject, 't');
        // We want to make sure we do not end up in "d"
        $this->assertTrue($marking->has($this->getTypedPlaceValue('b', $useEnumerations)));
        $this->assertFalse($marking->has($this->getTypedPlaceValue('d', $useEnumerations)));
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testApplyWithEventDispatcher(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $eventDispatcher = new EventDispatcherMock();
        $workflow = new Workflow($definition, new MethodMarkingStore(), $eventDispatcher, 'workflow_name');

        $eventNameExpected = [
            'workflow.entered',
            'workflow.workflow_name.entered',
            'workflow.workflow_name.entered.'.$this->getPlaceEventSuffix('a', $useEnumerations),
            'workflow.guard',
            'workflow.workflow_name.guard',
            'workflow.workflow_name.guard.t1',
            'workflow.leave',
            'workflow.workflow_name.leave',
            'workflow.workflow_name.leave.'.$this->getPlaceEventSuffix('a', $useEnumerations),
            'workflow.transition',
            'workflow.workflow_name.transition',
            'workflow.workflow_name.transition.t1',
            'workflow.enter',
            'workflow.workflow_name.enter',
            'workflow.workflow_name.enter.'.$this->getPlaceEventSuffix('b', $useEnumerations),
            'workflow.workflow_name.enter.'.$this->getPlaceEventSuffix('c', $useEnumerations),
            'workflow.entered',
            'workflow.workflow_name.entered',
            'workflow.workflow_name.entered.'.$this->getPlaceEventSuffix('b', $useEnumerations),
            'workflow.workflow_name.entered.'.$this->getPlaceEventSuffix('c', $useEnumerations),
            'workflow.completed',
            'workflow.workflow_name.completed',
            'workflow.workflow_name.completed.t1',
            // Following events are fired because of announce() method
            'workflow.announce',
            'workflow.workflow_name.announce',
            'workflow.guard',
            'workflow.workflow_name.guard',
            'workflow.workflow_name.guard.t2',
            'workflow.workflow_name.announce.t2',
        ];

        $workflow->apply($subject, 't1');

        $this->assertSame($eventNameExpected, $eventDispatcher->dispatchedEvents);
    }

    public function provideApplyWithEventDispatcherForAnnounceTests()
    {
        yield [false, [Workflow::DISABLE_ANNOUNCE_EVENT => true]];
        yield [true, [Workflow::DISABLE_ANNOUNCE_EVENT => false]];
        yield [true, []];
    }

    /** @dataProvider provideApplyWithEventDispatcherForAnnounceTests */
    public function testApplyWithEventDispatcherForAnnounce(bool $fired, array $context)
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new Subject();
        $eventDispatcher = new EventDispatcherMock();
        $workflow = new Workflow($definition, new MethodMarkingStore(), $eventDispatcher, 'workflow_name');

        $workflow->apply($subject, 't1', $context);

        if ($fired) {
            $this->assertContains('workflow.workflow_name.announce', $eventDispatcher->dispatchedEvents);
        } else {
            $this->assertNotContains('workflow.workflow_name.announce', $eventDispatcher->dispatchedEvents);
        }
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testApplyDispatchesWithDisableEventInContext(bool $useEnumerations)
    {
        $transitions[] = new Transition('a-b', $this->getTypedPlaceValue('a', $useEnumerations), $this->getTypedPlaceValue('b', $useEnumerations));
        $transitions[] = new Transition('a-c', $this->getTypedPlaceValue('a', $useEnumerations), $this->getTypedPlaceValue('c', $useEnumerations));
        $definition = new Definition($useEnumerations ? AlphabeticalEnum::cases() : ['a', 'b', 'c'], $transitions);

        $subject = new Subject();
        $eventDispatcher = new EventDispatcherMock();
        $workflow = new Workflow($definition, new MethodMarkingStore(), $eventDispatcher, 'workflow_name');

        $eventNameExpected = [
            'workflow.guard',
            'workflow.workflow_name.guard',
            'workflow.workflow_name.guard.a-b',
            'workflow.transition',
            'workflow.workflow_name.transition',
            'workflow.workflow_name.transition.a-b',
        ];

        $workflow->apply($subject, 'a-b', [
            Workflow::DISABLE_LEAVE_EVENT => true,
            Workflow::DISABLE_ENTER_EVENT => true,
            Workflow::DISABLE_ENTERED_EVENT => true,
            Workflow::DISABLE_COMPLETED_EVENT => true,
            Workflow::DISABLE_ANNOUNCE_EVENT => true,
        ]);

        $this->assertSame($eventNameExpected, $eventDispatcher->dispatchedEvents);
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testApplyDispatchesNoEventsWhenSpecifiedByDefinition(bool $useEnumerations)
    {
        $transitions[] = new Transition('a-b', $this->getTypedPlaceValue('a', $useEnumerations), $this->getTypedPlaceValue('b', $useEnumerations));
        $transitions[] = new Transition('a-c', $this->getTypedPlaceValue('a', $useEnumerations), $this->getTypedPlaceValue('c', $useEnumerations));
        $definition = new Definition($useEnumerations ? AlphabeticalEnum::cases() : ['a', 'b', 'c'], $transitions);

        $subject = new Subject();
        $eventDispatcher = new EventDispatcherMock();
        $workflow = new Workflow($definition, new MethodMarkingStore(), $eventDispatcher, 'workflow_name', []);

        $eventNameExpected = [
            'workflow.guard',
            'workflow.workflow_name.guard',
            'workflow.workflow_name.guard.a-b',
        ];

        $workflow->apply($subject, 'a-b');

        $this->assertSame($eventNameExpected, $eventDispatcher->dispatchedEvents);
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testApplyOnlyDispatchesEventsThatHaveBeenSpecifiedByDefinition(bool $useEnumerations)
    {
        $transitions[] = new Transition('a-b', $this->getTypedPlaceValue('a', $useEnumerations), $this->getTypedPlaceValue('b', $useEnumerations));
        $transitions[] = new Transition('a-c', $this->getTypedPlaceValue('a', $useEnumerations), $this->getTypedPlaceValue('c', $useEnumerations));
        $definition = new Definition($useEnumerations ? AlphabeticalEnum::cases() : ['a', 'b', 'c'], $transitions);

        $subject = new Subject();
        $eventDispatcher = new EventDispatcherMock();
        $workflow = new Workflow($definition, new MethodMarkingStore(), $eventDispatcher, 'workflow_name', [WorkflowEvents::COMPLETED]);

        $eventNameExpected = [
            'workflow.guard',
            'workflow.workflow_name.guard',
            'workflow.workflow_name.guard.a-b',
            'workflow.completed',
            'workflow.workflow_name.completed',
            'workflow.workflow_name.completed.a-b',
        ];

        $workflow->apply($subject, 'a-b');

        $this->assertSame($eventNameExpected, $eventDispatcher->dispatchedEvents);
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testApplyDoesNotTriggerExtraGuardWithEventDispatcher(bool $useEnumerations)
    {
        $transitions[] = new Transition('a-b', $this->getTypedPlaceValue('a', $useEnumerations), $this->getTypedPlaceValue('b', $useEnumerations));
        $transitions[] = new Transition('a-c', $this->getTypedPlaceValue('a', $useEnumerations), $this->getTypedPlaceValue('c', $useEnumerations));
        $definition = new Definition($useEnumerations ? AlphabeticalEnum::cases() : ['a', 'b', 'c'], $transitions);

        $subject = new Subject();
        $eventDispatcher = new EventDispatcherMock();
        $workflow = new Workflow($definition, new MethodMarkingStore(), $eventDispatcher, 'workflow_name');

        $eventNameExpected = [
            'workflow.entered',
            'workflow.workflow_name.entered',
            'workflow.workflow_name.entered.'.$this->getPlaceEventSuffix('a', $useEnumerations),
            'workflow.guard',
            'workflow.workflow_name.guard',
            'workflow.workflow_name.guard.a-b',
            'workflow.leave',
            'workflow.workflow_name.leave',
            'workflow.workflow_name.leave.'.$this->getPlaceEventSuffix('a', $useEnumerations),
            'workflow.transition',
            'workflow.workflow_name.transition',
            'workflow.workflow_name.transition.a-b',
            'workflow.enter',
            'workflow.workflow_name.enter',
            'workflow.workflow_name.enter.'.$this->getPlaceEventSuffix('b', $useEnumerations),
            'workflow.entered',
            'workflow.workflow_name.entered',
            'workflow.workflow_name.entered.'.$this->getPlaceEventSuffix('b', $useEnumerations),
            'workflow.completed',
            'workflow.workflow_name.completed',
            'workflow.workflow_name.completed.a-b',
            'workflow.announce',
            'workflow.workflow_name.announce',
        ];

        $workflow->apply($subject, 'a-b');

        $this->assertSame($eventNameExpected, $eventDispatcher->dispatchedEvents);
    }

    public function testApplyWithContext()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new Subject();
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener('workflow.transition', function (TransitionEvent $event) {
            $event->setContext(array_merge($event->getContext(), ['user' => 'admin']));
        });
        $workflow = new Workflow($definition, new MethodMarkingStore(), $eventDispatcher);

        $workflow->apply($subject, 't1', ['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar', 'user' => 'admin'], $subject->getContext());
    }

    public function testEventName()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new Subject();
        $dispatcher = new EventDispatcher();
        $name = 'workflow_name';
        $workflow = new Workflow($definition, new MethodMarkingStore(), $dispatcher, $name);

        $assertWorkflowName = function (Event $event) use ($name) {
            $this->assertEquals($name, $event->getWorkflowName());
        };

        $eventNames = [
            'workflow.guard',
            'workflow.leave',
            'workflow.transition',
            'workflow.enter',
            'workflow.entered',
            'workflow.announce',
        ];

        foreach ($eventNames as $eventName) {
            $dispatcher->addListener($eventName, $assertWorkflowName);
        }

        $workflow->apply($subject, 't1');
    }

    public function testEventContext()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new Subject();
        $dispatcher = new EventDispatcher();
        $name = 'workflow_name';
        $context = ['context'];
        $workflow = new Workflow($definition, new MethodMarkingStore(), $dispatcher, $name);

        $assertWorkflowContext = function (Event $event) use ($context) {
            $this->assertEquals($context, $event->getContext());
        };

        $eventNames = [
            'workflow.leave',
            'workflow.transition',
            'workflow.enter',
            'workflow.entered',
            'workflow.announce',
        ];

        foreach ($eventNames as $eventName) {
            $dispatcher->addListener($eventName, $assertWorkflowContext);
        }

        $marking = $workflow->apply($subject, 't1', $context);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertSame($context, $marking->getContext());
    }

    public function testEventContextUpdated()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new Subject();
        $dispatcher = new EventDispatcher();

        $workflow = new Workflow($definition, new MethodMarkingStore(), $dispatcher);

        $dispatcher->addListener('workflow.transition', function (TransitionEvent $event) {
            $event->setContext(['foo' => 'bar']);
        });

        $marking = $workflow->apply($subject, 't1', ['initial']);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertSame(['foo' => 'bar'], $marking->getContext());
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testEventDefaultInitialContext(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $dispatcher = new EventDispatcher();
        $name = 'workflow_name';
        $context = Workflow::DEFAULT_INITIAL_CONTEXT;
        $workflow = new Workflow($definition, new MethodMarkingStore(), $dispatcher, $name);

        $assertWorkflowContext = function (Event $event) use ($context) {
            $this->assertEquals($context, $event->getContext());
        };

        $eventNames = [
            'workflow.workflow_name.entered.'.$this->getPlaceEventSuffix('a', $useEnumerations),
        ];

        foreach ($eventNames as $eventName) {
            $dispatcher->addListener($eventName, $assertWorkflowContext);
        }

        $workflow->apply($subject, 't1');
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testMarkingStateOnApplyWithEventDispatcher(bool $useEnumerations)
    {
        $definition = new Definition($useEnumerations ? AlphabeticalEnum::cases() : range('a', 'f'),
            [
                new Transition('t',
                    $useEnumerations ?
                        [
                            AlphabeticalEnum::A,
                            AlphabeticalEnum::B,
                            AlphabeticalEnum::C,
                        ] : range('a', 'c'),
                    $useEnumerations ?
                        [
                            AlphabeticalEnum::D,
                            AlphabeticalEnum::E,
                            AlphabeticalEnum::F,
                        ] : range('d', 'f'),
                ),
            ]);

        $subject = new Subject();
        $subject->setMarking($useEnumerations ? [
            AlphabeticalEnum::A,
            AlphabeticalEnum::B,
            AlphabeticalEnum::C,
        ] : ['a' => 1, 'b' => 1, 'c' => 1]);

        $dispatcher = new EventDispatcher();

        $workflow = new Workflow($definition, new MethodMarkingStore(), $dispatcher, 'test');

        $assertInitialState = function (Event $event) use ($useEnumerations) {
            $this->assertEquals(new Marking($useEnumerations ? [AlphabeticalEnum::A, AlphabeticalEnum::B, AlphabeticalEnum::C] : ['a' => 1, 'b' => 1, 'c' => 1]), $event->getMarking());
        };
        $assertTransitionState = function (Event $event) {
            $this->assertEquals(new Marking([]), $event->getMarking());
        };

        $dispatcher->addListener('workflow.leave', $assertInitialState);
        $dispatcher->addListener('workflow.test.leave', $assertInitialState);
        $dispatcher->addListener('workflow.test.leave.'.$this->getPlaceEventSuffix('a', $useEnumerations), $assertInitialState);
        $dispatcher->addListener('workflow.test.leave.'.$this->getPlaceEventSuffix('b', $useEnumerations), $assertInitialState);
        $dispatcher->addListener('workflow.test.leave.'.$this->getPlaceEventSuffix('c', $useEnumerations), $assertInitialState);
        $dispatcher->addListener('workflow.transition', $assertTransitionState);
        $dispatcher->addListener('workflow.test.transition', $assertTransitionState);
        $dispatcher->addListener('workflow.test.transition.t', $assertTransitionState);
        $dispatcher->addListener('workflow.enter', $assertTransitionState);
        $dispatcher->addListener('workflow.test.enter', $assertTransitionState);
        $dispatcher->addListener('workflow.test.enter.'.$this->getPlaceEventSuffix('d', $useEnumerations), $assertTransitionState);
        $dispatcher->addListener('workflow.test.enter.'.$this->getPlaceEventSuffix('e', $useEnumerations), $assertTransitionState);
        $dispatcher->addListener('workflow.test.enter.'.$this->getPlaceEventSuffix('f', $useEnumerations), $assertTransitionState);

        $workflow->apply($subject, 't');
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testGetEnabledTransitions(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener('workflow.workflow_name.guard.t1', function (GuardEvent $event) {
            $event->setBlocked(true);
        });
        $workflow = new Workflow($definition, new MethodMarkingStore(), $eventDispatcher, 'workflow_name');

        $this->assertEmpty($workflow->getEnabledTransitions($subject));

        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::D] : ['d' => 1]);
        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(2, $transitions);
        $this->assertSame('t3', $transitions[0]->getName());
        $this->assertSame('t4', $transitions[1]->getName());

        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::C, AlphabeticalEnum::E] : ['c' => 1, 'e' => 1]);
        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(1, $transitions);
        $this->assertSame('t5', $transitions[0]->getName());
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testGetEnabledTransition(bool $useEnumerations)
    {
        $definition = $this->createComplexWorkflowDefinition($useEnumerations);
        $subject = new Subject();
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::D] : ['d' => 1]);
        $transition = $workflow->getEnabledTransition($subject, 't3');
        $this->assertInstanceOf(Transition::class, $transition);
        $this->assertSame('t3', $transition->getName());

        $transition = $workflow->getEnabledTransition($subject, 'does_not_exist');
        $this->assertNull($transition);
    }

    /**
     * @dataProvider provideUseEnumerations
     */
    public function testGetEnabledTransitionsWithSameNameTransition(bool $useEnumerations)
    {
        $definition = $this->createWorkflowWithSameNameTransition($useEnumerations);
        $subject = new Subject();
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(1, $transitions);
        $this->assertSame('a_to_bc', $transitions[0]->getName());

        $subject->setMarking($useEnumerations ? [AlphabeticalEnum::B, AlphabeticalEnum::C] : ['b' => 1, 'c' => 1]);
        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(3, $transitions);
        $this->assertSame('b_to_c', $transitions[0]->getName());
        $this->assertSame('to_a', $transitions[1]->getName());
        $this->assertSame('to_a', $transitions[2]->getName());
    }

    public function provideUseEnumerations(): \Generator
    {
        yield [false];

        if (\PHP_VERSION_ID >= 80100) {
            yield [true];
        }
    }
}

class EventDispatcherMock implements \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
{
    public $dispatchedEvents = [];

    public function dispatch($event, string $eventName = null): object
    {
        $this->dispatchedEvents[] = $eventName;

        return $event;
    }
}
