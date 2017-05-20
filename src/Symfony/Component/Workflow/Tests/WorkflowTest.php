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

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\LogicException
     * @expectedExceptionMessage Unable to apply transition "t2" for workflow "unnamed".
     */
    public function testApplyWithImpossibleTransition()
    {
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

        $subject->marking = array('b' => 1);
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
            // Following events are fired because of announce() method
            'workflow.guard',
            'workflow.workflow_name.guard',
            'workflow.workflow_name.guard.t2',
            'workflow.workflow_name.announce.t2',
        );

        $marking = $workflow->apply($subject, 't1');

        $this->assertSame($eventNameExpected, $eventDispatcher->dispatchedEvents);
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
