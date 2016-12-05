<?php

namespace Symfony\Component\Workflow\Tests;

use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\StateMachine;

class StateMachineTest extends \PHPUnit_Framework_TestCase
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
}
