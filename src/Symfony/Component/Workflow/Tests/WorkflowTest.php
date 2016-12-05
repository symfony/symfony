<?php

namespace Symfony\Component\Workflow\Tests;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\MultipleStateMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

class WorkflowTest extends \PHPUnit_Framework_TestCase
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
        $subject->marking = array('nope' => true);
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

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\LogicException
     * @expectedExceptionMessage Transition "foobar" does not exist for workflow "unnamed".
     */
    public function testCanWithUnexistingTransition()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $workflow->can($subject, 'foobar');
    }

    public function testCan()
    {
        $definition = $this->createComplexWorkflowDefinition();
        $subject = new \stdClass();
        $subject->marking = null;
        $workflow = new Workflow($definition, new MultipleStateMarkingStore());

        $this->assertTrue($workflow->can($subject, 't1'));
        $this->assertFalse($workflow->can($subject, 't2'));
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
            // Following events are fired because of announce() method
            'workflow.guard',
            'workflow.workflow_name.guard',
            'workflow.workflow_name.guard.t2',
            'workflow.workflow_name.announce.t2',
        );

        $marking = $workflow->apply($subject, 't1');

        $this->assertSame($eventNameExpected, $eventDispatcher->dispatchedEvents);
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

        $subject->marking = array('d' => true);
        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(2, $transitions);
        $this->assertSame('t3', $transitions[0]->getName());
        $this->assertSame('t4', $transitions[1]->getName());

        $subject->marking = array('c' => true, 'e' => true);
        $transitions = $workflow->getEnabledTransitions($subject);
        $this->assertCount(1, $transitions);
        $this->assertSame('t5', $transitions[0]->getName());
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
