<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\StateMachine;

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
}
