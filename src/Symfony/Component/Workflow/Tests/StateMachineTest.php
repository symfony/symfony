<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\TransitionBlocker;

class StateMachineTest extends TestCase
{
    use WorkflowBuilderTrait;

    public function testCan()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $net = new StateMachine($definition);
        $subject = new \stdClass();

        // If you are in place "a" you should be able to apply "t1"
        $subject->marking = 'a';
        $this->assertTrue($net->can($subject, 't1'));
        $subject->marking = 'd';
        $this->assertTrue($net->can($subject, 't1'));

        $subject->marking = 'b';
        $this->assertFalse($net->can($subject, 't1'));
    }

    public function testCanWithMultipleTransition()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $net = new StateMachine($definition);
        $subject = new \stdClass();

        // If you are in place "b" you should be able to apply "t1" and "t2"
        $subject->marking = 'b';
        $this->assertTrue($net->can($subject, 't2'));
        $this->assertTrue($net->can($subject, 't3'));
    }

    public function testBuildTransitionBlockerList()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $net = new StateMachine($definition);
        $subject = new \stdClass();

        // If you are in place "a" you should be able to apply "t1"
        $subject->marking = 'a';
        $this->assertTrue($net->buildTransitionBlockerList($subject, 't1')->isEmpty());
        $subject->marking = 'd';
        $this->assertTrue($net->buildTransitionBlockerList($subject, 't1')->isEmpty());

        $subject->marking = 'b';
        $this->assertFalse($net->buildTransitionBlockerList($subject, 't1')->isEmpty());
    }

    public function testBuildTransitionBlockerListWithMultipleTransition()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $net = new StateMachine($definition);
        $subject = new \stdClass();

        // If you are in place "b" you should be able to apply "t1" and "t2"
        $subject->marking = 'b';
        $this->assertTrue($net->buildTransitionBlockerList($subject, 't2')->isEmpty());
        $this->assertTrue($net->buildTransitionBlockerList($subject, 't3')->isEmpty());
    }

    public function testBuildTransitionBlockerListReturnsExpectedReasonOnBranchMerge()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $dispatcher = new EventDispatcher();
        $net = new StateMachine($definition, null, $dispatcher);

        $dispatcher->addListener('workflow.guard', function (GuardEvent $event) {
            $event->addTransitionBlocker(new TransitionBlocker(\sprintf('Transition blocker of place %s', $event->getTransition()->getFroms()[0]), 'blocker'));
        });

        $subject = new \stdClass();

        // When there are multiple transitions with the same name then method buildTransitionBlockerList might
        // return result of different transition than expected.
        // For example: buildTransitionBlockerList foreach two transitions "t1" where one starts in place "a" and second in place "d" and
        // we are currently in place "a". Method buildTransitionBlockerList first processes transition "t1" with from place "a"
        // but there is no break so it continues to transition "t1" with from place "d" where it exits loop because there are no more
        // transitions with name "t1". So returned transition blocker list contains result of transition "t1" with from place "d" because
        // it was executed lastest. This result is incorrect because we got "Blocked by marking" transition blocker of transition "t1"
        // with from place "d" instead of guard blocker "Transition blocker of place a" of transition "t1" with from place "a".
        // This test checks if this bug does not happen.

        // Test if when you are in place "a" applying transition "t1" then returned blocker list contains guard blocker instead blockedByMarking
        $subject->marking = 'a';
        $transitionBlockerList = $net->buildTransitionBlockerList($subject, 't1');
        $this->assertCount(1, $transitionBlockerList);
        $blockers = iterator_to_array($transitionBlockerList);

        $this->assertSame('Transition blocker of place a', $blockers[0]->getMessage());
        $this->assertSame('blocker', $blockers[0]->getCode());

        // Test if when you are in place "d" applying transition "t1" then returned blocker list contains guard blocker instead blockedByMarking
        $subject->marking = 'd';
        $transitionBlockerList = $net->buildTransitionBlockerList($subject, 't1');
        $this->assertCount(1, $transitionBlockerList);
        $blockers = iterator_to_array($transitionBlockerList);

        $this->assertSame('Transition blocker of place d', $blockers[0]->getMessage());
        $this->assertSame('blocker', $blockers[0]->getCode());
    }
}
