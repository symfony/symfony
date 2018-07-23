<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\MultipleStateMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\Workflow;

class WorkflowTest extends TestCase
{
    use WorkflowBuilderTrait;

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\LogicException
     * @expectedExceptionMessage The value returned by the MarkingStore is not an instance of "Symfony\Component\Workflow\Marking" for workflow "unnamed".
     */
    public function testGetMarkingWithInvalidStoreReturn()
    {
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow(new Definition(array(), array()), $this->getMockBuilder(MarkingStoreInterface::class)->getMock());

        $workflow->getMarking($subject);
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\LogicException
     * @expectedExceptionMessage The Marking is empty and there is no initial place for workflow "unnamed".
     */
    public function testGetMarkingWithEmptyDefinition()
    {
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow(new Definition(array(), array()), new MultipleStateMarkingStore());

        $workflow->getMarking($subject);
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\LogicException
     * @expectedExceptionMessage Place "nope" is not valid for workflow "unnamed".
     */
    public function testGetMarkingWithImpossiblePlace()
    {
        $subject = new \stdClass();
        $subject->marking = array('nope' => 1);
        $workflow = new Workflow(new Definition(array(), array()), new MultipleStateMarkingStore());

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
        $this->assertSame(array('a' => 1), $subject->marking);
    }

    public function testGetMarkingWithExistingMarking()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $subject->marking = array('b' => 1, 'c' => 1);
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

        $subject->marking = array('b' => 1);

        $this->assertFalse($workflow->can($subject, 't1'));
        // In a workflow net, all "from" places should contain a token to enable
        // the transition.
        $this->assertFalse($workflow->can($subject, 't2'));

        $subject->marking = array('b' => 1, 'c' => 1);

        $this->assertFalse($workflow->can($subject, 't1'));
        $this->assertTrue($workflow->can($subject, 't2'));

        $subject->marking = array('f' => 1);

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

        $dispatchedEvents = array();
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

        $this->assertSame(array('workflow_name.guard.t3'), $dispatchedEvents);
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

        $subject->marking = array('b' => 1);
        $this->assertFalse($workflow->can($subject, 'a_to_bc'));
        $this->assertTrue($workflow->can($subject, 'b_to_c'));
        $this->assertTrue($workflow->can($subject, 'to_a'));
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\UndefinedTransitionException
     * @expectedExceptionMessage Transition "404 Not Found" is not defined for workflow "unnamed".
     */
    public function testBuildTransitionBlockerListReturnsUndefinedTransition()
    {
        $definition = $this->createSimpleWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition);

        $workflow->buildTransitionBlockerList($subject, '404 Not Found');
    }

    public function testBuildTransitionBlockerListReturnsReasonsProvidedByMarking()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $transitionBlockerList = $workflow->buildTransitionBlockerList($subject, 't2');
        $this->assertCount(1, $transitionBlockerList);
        $blockers = iterator_to_array($transitionBlockerList);
        $this->assertSame('The marking does not enable the transition.', $blockers[0]->getMessage());
        $this->assertSame('19beefc8-6b1e-4716-9d07-a39bd6d16e34', $blockers[0]->getCode());
    }

    public function testBuildTransitionBlockerListReturnsReasonsProvidedInGuards()
    {
        $definition = $this->createSimpleWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $dispatcher = new EventDispatcher();
        $workflow = new Workflow($definition, new MultipleStateMarkingStore(), $dispatcher);

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

        $transitionBlockerList = $workflow->buildTransitionBlockerList($subject, 't1');
        $this->assertCount(4, $transitionBlockerList);
        $blockers = iterator_to_array($transitionBlockerList);
        $this->assertSame('Transition blocker 1', $blockers[0]->getMessage());
        $this->assertSame('blocker_1', $blockers[0]->getCode());
        $this->assertSame('Transition blocker 2', $blockers[1]->getMessage());
        $this->assertSame('blocker_2', $blockers[1]->getCode());
        $this->assertSame('Transition blocker 3', $blockers[2]->getMessage());
        $this->assertSame('blocker_3', $blockers[2]->getCode());
        $this->assertSame('Unknown reason.', $blockers[3]->getMessage());
        $this->assertSame('e8b5bbb9-5913-4b98-bfa6-65dbd228a82a', $blockers[3]->getCode());
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\UndefinedTransitionException
     * @expectedExceptionMessage Transition "404 Not Found" is not defined for workflow "unnamed".
     */
    public function testApplyWithNotExisingTransition()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $workflow->apply($subject, '404 Not Found');
    }

    public function testApplyWithNotEnabledTransition()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        try {
            $workflow->apply($subject, 't2');

            $this->fail('Should throw an exception');
        } catch (NotEnabledTransitionException $e) {
            $this->assertSame('Transition "t2" is not enabled for workflow "unnamed".', $e->getMessage());
            $this->assertCount(1, $e->getTransitionBlockerList());
            $list = iterator_to_array($e->getTransitionBlockerList());
            $this->assertSame('The marking does not enable the transition.', $list[0]->getMessage());
            $this->assertSame($e->getWorkflow(), $workflow);
            $this->assertSame($e->getSubject(), $subject);
            $this->assertSame($e->getTransitionName(), 't2');
        }
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

        $marking = $workflow->apply($subject, 'a_to_bc');
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
        $subject->marking = array('a' => 1, 'b' => 1);

        $places = range('a', 'd');
        $transitions = array();
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

        $eventNameExpected = array(
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
        );

        $marking = $workflow->apply($subject, 't1');

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

        $eventNames = array(
            'workflow.guard',
            'workflow.leave',
            'workflow.transition',
            'workflow.enter',
            'workflow.entered',
            'workflow.announce',
        );

        foreach ($eventNames as $eventName) {
            $dispatcher->addListener($eventName, $assertWorkflowName);
        }

        $workflow->apply($subject, 't1');
    }

    public function testMarkingStateOnApplyWithEventDispatcher()
    {
        $definition = new Definition(range('a', 'f'), array(new Transition('t', range('a', 'c'), range('d', 'f'))));

        $subject = new \stdClass();
        $subject->marking = array('a' => 1, 'b' => 1, 'c' => 1);

        $dispatcher = new EventDispatcher();

        $workflow = new Workflow($definition, new MultipleStateMarkingStore(), $dispatcher, 'test');

        $assertInitialState = function (Event $event) {
            $this->assertEquals(new Marking(array('a' => 1, 'b' => 1, 'c' => 1)), $event->getMarking());
        };
        $assertTransitionState = function (Event $event) {
            $this->assertEquals(new Marking(array()), $event->getMarking());
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

        $subject->marking = array('d' => 1);
        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(2, $transitions);
        $this->assertSame('t3', $transitions[0]->getName());
        $this->assertSame('t4', $transitions[1]->getName());

        $subject->marking = array('c' => 1, 'e' => 1);
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

        $subject->marking = array('b' => 1, 'c' => 1);
        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(3, $transitions);
        $this->assertSame('b_to_c', $transitions[0]->getName());
        $this->assertSame('to_a', $transitions[1]->getName());
        $this->assertSame('to_a', $transitions[2]->getName());
    }
}

class EventDispatcherMock implements \Symfony\Component\EventDispatcher\EventDispatcherInterface
{
    public $dispatchedEvents = array();

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
