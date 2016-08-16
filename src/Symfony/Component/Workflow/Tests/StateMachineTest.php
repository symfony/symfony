<?php

namespace Symfony\Component\Workflow\Tests;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;

class StateMachineTest extends \PHPUnit_Framework_TestCase
{
    public function testCan()
    {
        $places = array('a', 'b', 'c', 'd');
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t1', 'd', 'b');
        $transitions[] = new Transition('t2', 'b', 'c');
        $transitions[] = new Transition('t3', 'b', 'd');
        $definition = new Definition($places, $transitions);

        $net = new StateMachine($definition);
        $subject = new \stdClass();

        // If you are in place "a" you should be able to apply "t1"
        $subject->marking = 'a';
        $this->assertTrue($net->can($subject, 't1'));
        $subject->marking = 'd';
        $this->assertTrue($net->can($subject, 't1'));

        $subject->marking = 'b';
        $this->assertFalse($net->can($subject, 't1'));

        // The graph looks like:
        //
        //                        +-------------------------------+
        //                        v                               |
        // +---+     +----+     +----+     +----+     +---+     +----+
        // | a | --> | t1 | --> | b  | --> | t3 | --> | d | --> | t1 |
        // +---+     +----+     +----+     +----+     +---+     +----+
        //                        |
        //                        |
        //                        v
        //                      +----+     +----+
        //                      | t2 | --> | c  |
        //                      +----+     +----+
    }

    public function testCanWithMultipleTransition()
    {
        $places = array('a', 'b', 'c');
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t2', 'a', 'c');
        $definition = new Definition($places, $transitions);

        $net = new StateMachine($definition);
        $subject = new \stdClass();

        // If you are in place "a" you should be able to apply "t1" and "t2"
        $subject->marking = 'a';
        $this->assertTrue($net->can($subject, 't1'));
        $this->assertTrue($net->can($subject, 't2'));

        // The graph looks like:
        //
        // +----+     +----+     +---+
        // | a  | --> | t1 | --> | b |
        // +----+     +----+     +---+
        //   |
        //   |
        //   v
        // +----+     +----+
        // | t2 | --> | c  |
        // +----+     +----+
    }
}
