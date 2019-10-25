<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\MultipleStateMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

class WorkflowTest extends TestCase
{
    use WorkflowBuilderTrait;

    public function testGetMarkingWithInvalidStoreReturn()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\LogicException');
        $this->expectExceptionMessage('The value returned by the MarkingStore is not an instance of "Symfony\Component\Workflow\Marking" for workflow "unnamed".');
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow(new Definition([], []), $this->getMockBuilder(MarkingStoreInterface::class)->getMock());

        $workflow->getMarking($subject);
    }

    public function testGetMarkingWithEmptyDefinition()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\LogicException');
        $this->expectExceptionMessage('The Marking is empty and there is no initial place for workflow "unnamed".');
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow(new Definition([], []), new MultipleStateMarkingStore());

        $workflow->getMarking($subject);
    }

    public function testGetMarkingWithImpossiblePlace()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\LogicException');
        $this->expectExceptionMessage('Place "nope" is not valid for workflow "unnamed".');
        $subject = new \stdClass();
        $subject->marking = ['nope' => 1];
        $workflow = new Workflow(new Definition([], []), new MultipleStateMarkingStore());

        $workflow->getMarking($subject);
    }

    public function testGetMarkingWithEmptyInitialMarking()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $marking = $workflow->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertTrue($marking->has('a'));
        $this->assertSame(['a' => 1], $subject->marking);
    }

    public function testGetMarkingWithExistingMarking()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $subject->marking = ['b' => 1, 'c' => 1];
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $marking = $workflow->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertTrue($marking->has('b'));
        $this->assertTrue($marking->has('c'));
    }

    public function testCanWithUnexistingTransition()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $this->assertFalse($workflow->can($subject, 'foobar'));
    }

    public function testCan()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $this->assertTrue($workflow->can($subject, 't1'));
        $this->assertFalse($workflow->can($subject, 't2'));

        $subject->marking = ['b' => 1];

        $this->assertFalse($workflow->can($subject, 't1'));
        // In a workflow net, all "from" places should contain a token to enable
        // the transition.
        $this->assertFalse($workflow->can($subject, 't2'));

        $subject->marking = ['b' => 1, 'c' => 1];

        $this->assertFalse($workflow->can($subject, 't1'));
        $this->assertTrue($workflow->can($subject, 't2'));

        $subject->marking = ['f' => 1];

        $this->assertFalse($workflow->can($subject, 't5'));
        $this->assertTrue($workflow->can($subject, 't6'));
    }

    public function testCanWithGuard()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener('workflow.workflow_name.guard.t1', function (GuardEvent $event) {
            $event->setBlocked(true);
        });
        $workflow = new Workflow($definition, new MultipleStateMarkingStore(), $eventDispatcher, 'workflow_name');

        $this->assertFalse($workflow->can($subject, 't1'));
    }

    public function testCanDoesNotTriggerGuardEventsForNotEnabledTransitions()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;

        $dispatchedEvents = [];
        $eventDispatcher = new EventDispatcher();

        $workflow = new Workflow($definition, new MultipleStateMarkingStore(), $eventDispatcher, 'workflow_name');
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

    public function testApplyWithImpossibleTransition()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\LogicException');
        $this->expectExceptionMessage('Unable to apply transition "t2" for workflow "unnamed".');
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $workflow->apply($subject, 't2');
    }

    public function testCanWithSameNameTransition()
    {
        $definition = $this->createWorkflowWithSameNameTransition();
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $subject = new \stdClass();
        $subject->marking = null;
        $this->assertTrue($workflow->can($subject, 'a_to_bc'));
        $this->assertFalse($workflow->can($subject, 'b_to_c'));
        $this->assertFalse($workflow->can($subject, 'to_a'));

        $subject->marking = ['b' => 1];
        $this->assertFalse($workflow->can($subject, 'a_to_bc'));
        $this->assertTrue($workflow->can($subject, 'b_to_c'));
        $this->assertTrue($workflow->can($subject, 'to_a'));
    }

    public function testApply()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $marking = $workflow->apply($subject, 't1');

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertFalse($marking->has('a'));
        $this->assertTrue($marking->has('b'));
        $this->assertTrue($marking->has('c'));
    }

    public function testApplyWithSameNameTransition()
    {
        $subject = new \stdClass();
        $subject->marking = null;
        $definition = $this->createWorkflowWithSameNameTransition();
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $marking = $workflow->apply($subject, 'a_to_bc');

        $this->assertFalse($marking->has('a'));
        $this->assertTrue($marking->has('b'));
        $this->assertTrue($marking->has('c'));

        $marking = $workflow->apply($subject, 'to_a');

        $this->assertTrue($marking->has('a'));
        $this->assertFalse($marking->has('b'));
        $this->assertFalse($marking->has('c'));

        $workflow->apply($subject, 'a_to_bc');
        $marking = $workflow->apply($subject, 'b_to_c');

        $this->assertFalse($marking->has('a'));
        $this->assertFalse($marking->has('b'));
        $this->assertTrue($marking->has('c'));

        $marking = $workflow->apply($subject, 'to_a');

        $this->assertTrue($marking->has('a'));
        $this->assertFalse($marking->has('b'));
        $this->assertFalse($marking->has('c'));
    }

    public function testApplyWithSameNameTransition2()
    {
        $subject = new \stdClass();
        $subject->marking = ['a' => 1, 'b' => 1];

        $places = range('a', 'd');
        $transitions = [];
        $transitions[] = new Transition('t', 'a', 'c');
        $transitions[] = new Transition('t', 'b', 'd');
        $definition = new Definition($places, $transitions);
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $marking = $workflow->apply($subject, 't');

        $this->assertFalse($marking->has('a'));
        $this->assertFalse($marking->has('b'));
        $this->assertTrue($marking->has('c'));
        $this->assertTrue($marking->has('d'));
    }

    public function testApplyWithEventDispatcher()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $eventDispatcher = new EventDispatcherMock();
        $workflow = new Workflow($definition, new MultipleStateMarkingStore(), $eventDispatcher, 'workflow_name');

        $eventNameExpected = [
            'workflow.guard',
            'workflow.workflow_name.guard',
            'workflow.workflow_name.guard.t1',
            'workflow.leave',
            'workflow.workflow_name.leave',
            'workflow.workflow_name.leave.a',
            'workflow.transition',
            'workflow.workflow_name.transition',
            'workflow.workflow_name.transition.t1',
            'workflow.enter',
            'workflow.workflow_name.enter',
            'workflow.workflow_name.enter.b',
            'workflow.workflow_name.enter.c',
            'workflow.entered',
            'workflow.workflow_name.entered',
            'workflow.workflow_name.entered.b',
            'workflow.workflow_name.entered.c',
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

    public function testApplyDoesNotTriggerExtraGuardWithEventDispatcher()
    {
        $transitions[] = new Transition('a-b', 'a', 'b');
        $transitions[] = new Transition('a-c', 'a', 'c');
        $definition = new Definition(['a', 'b', 'c'], $transitions);

        $subject = new \stdClass();
        $subject->marking = null;
        $eventDispatcher = new EventDispatcherMock();
        $workflow = new Workflow($definition, new MultipleStateMarkingStore(), $eventDispatcher, 'workflow_name');

        $eventNameExpected = [
            'workflow.guard',
            'workflow.workflow_name.guard',
            'workflow.workflow_name.guard.a-b',
            'workflow.leave',
            'workflow.workflow_name.leave',
            'workflow.workflow_name.leave.a',
            'workflow.transition',
            'workflow.workflow_name.transition',
            'workflow.workflow_name.transition.a-b',
            'workflow.enter',
            'workflow.workflow_name.enter',
            'workflow.workflow_name.enter.b',
            'workflow.entered',
            'workflow.workflow_name.entered',
            'workflow.workflow_name.entered.b',
            'workflow.completed',
            'workflow.workflow_name.completed',
            'workflow.workflow_name.completed.a-b',
            'workflow.announce',
            'workflow.workflow_name.announce',
        ];

        $workflow->apply($subject, 'a-b');

        $this->assertSame($eventNameExpected, $eventDispatcher->dispatchedEvents);
    }

    public function testEventName()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $dispatcher = new EventDispatcher();
        $name = 'workflow_name';
        $workflow = new Workflow($definition, new MultipleStateMarkingStore(), $dispatcher, $name);

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

    public function testMarkingStateOnApplyWithEventDispatcher()
    {
        $definition = new Definition(range('a', 'f'), [new Transition('t', range('a', 'c'), range('d', 'f'))]);

        $subject = new \stdClass();
        $subject->marking = ['a' => 1, 'b' => 1, 'c' => 1];

        $dispatcher = new EventDispatcher();

        $workflow = new Workflow($definition, new MultipleStateMarkingStore(), $dispatcher, 'test');

        $assertInitialState = function (Event $event) {
            $this->assertEquals(new Marking(['a' => 1, 'b' => 1, 'c' => 1]), $event->getMarking());
        };
        $assertTransitionState = function (Event $event) {
            $this->assertEquals(new Marking([]), $event->getMarking());
        };

        $dispatcher->addListener('workflow.leave', $assertInitialState);
        $dispatcher->addListener('workflow.test.leave', $assertInitialState);
        $dispatcher->addListener('workflow.test.leave.a', $assertInitialState);
        $dispatcher->addListener('workflow.test.leave.b', $assertInitialState);
        $dispatcher->addListener('workflow.test.leave.c', $assertInitialState);
        $dispatcher->addListener('workflow.transition', $assertTransitionState);
        $dispatcher->addListener('workflow.test.transition', $assertTransitionState);
        $dispatcher->addListener('workflow.test.transition.t', $assertTransitionState);
        $dispatcher->addListener('workflow.enter', $assertTransitionState);
        $dispatcher->addListener('workflow.test.enter', $assertTransitionState);
        $dispatcher->addListener('workflow.test.enter.d', $assertTransitionState);
        $dispatcher->addListener('workflow.test.enter.e', $assertTransitionState);
        $dispatcher->addListener('workflow.test.enter.f', $assertTransitionState);

        $workflow->apply($subject, 't');
    }

    public function testGetEnabledTransitions()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener('workflow.workflow_name.guard.t1', function (GuardEvent $event) {
            $event->setBlocked(true);
        });
        $workflow = new Workflow($definition, new MultipleStateMarkingStore(), $eventDispatcher, 'workflow_name');

        $this->assertEmpty($workflow->getEnabledTransitions($subject));

        $subject->marking = ['d' => 1];
        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(2, $transitions);
        $this->assertSame('t3', $transitions[0]->getName());
        $this->assertSame('t4', $transitions[1]->getName());

        $subject->marking = ['c' => 1, 'e' => 1];
        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(1, $transitions);
        $this->assertSame('t5', $transitions[0]->getName());
    }

    public function testGetEnabledTransitionsWithSameNameTransition()
    {
        $definition = $this->createWorkflowWithSameNameTransition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(1, $transitions);
        $this->assertSame('a_to_bc', $transitions[0]->getName());

        $subject->marking = ['b' => 1, 'c' => 1];
        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(3, $transitions);
        $this->assertSame('b_to_c', $transitions[0]->getName());
        $this->assertSame('to_a', $transitions[1]->getName());
        $this->assertSame('to_a', $transitions[2]->getName());
    }
}

class EventDispatcherMock implements \Symfony\Component\EventDispatcher\EventDispatcherInterface
{
    public $dispatchedEvents = [];

    public function dispatch($eventName, \Symfony\Component\EventDispatcher\Event $event = null)
    {
        $this->dispatchedEvents[] = $eventName;
    }

    public function addListener($eventName, $listener, $priority = 0)
    {
    }

    public function addSubscriber(\Symfony\Component\EventDispatcher\EventSubscriberInterface $subscriber)
    {
    }

    public function removeListener($eventName, $listener)
    {
    }

    public function removeSubscriber(\Symfony\Component\EventDispatcher\EventSubscriberInterface $subscriber)
    {
    }

    public function getListeners($eventName = null)
    {
    }

    public function getListenerPriority($eventName, $listener)
    {
    }

    public function hasListeners($eventName = null)
    {
    }
}
