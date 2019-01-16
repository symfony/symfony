<?php

namespace Symfony\Component\Workflow\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Validator\StateMachineValidator;

class StateMachineValidatorTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidDefinitionException
     * @expectedExceptionMessage A transition from a place/state must have an unique name.
     */
    public function testWithMultipleTransitionWithSameNameShareInput()
    {
        $places = ['a', 'b', 'c'];
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t1', 'a', 'c');
        $definition = new Definition($places, $transitions);

        (new StateMachineValidator())->validate($definition, 'foo');

        // The graph looks like:
        //
        //   +----+     +----+     +---+
        //   | a  | --> | t1 | --> | b |
        //   +----+     +----+     +---+
        //    |
        //    |
        //    v
        //  +----+     +----+
        //  | t1 | --> | c  |
        //  +----+     +----+
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidDefinitionException
     * @expectedExceptionMessage A transition in StateMachine can only have one output.
     */
    public function testWithMultipleTos()
    {
        $places = ['a', 'b', 'c'];
        $transitions[] = new Transition('t1', 'a', ['b', 'c']);
        $definition = new Definition($places, $transitions);

        (new StateMachineValidator())->validate($definition, 'foo');

        // The graph looks like:
        //
        // +---+     +----+     +---+
        // | a | --> | t1 | --> | b |
        // +---+     +----+     +---+
        //             |
        //             |
        //             v
        //           +----+
        //           | c  |
        //           +----+
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidDefinitionException
     * @expectedExceptionMessage A transition in StateMachine can only have one input.
     */
    public function testWithMultipleFroms()
    {
        $places = ['a', 'b', 'c'];
        $transitions[] = new Transition('t1', ['a', 'b'], 'c');
        $definition = new Definition($places, $transitions);

        (new StateMachineValidator())->validate($definition, 'foo');

        // The graph looks like:
        //
        // +---+     +----+     +---+
        // | a | --> | t1 | --> | c |
        // +---+     +----+     +---+
        //             ^
        //             |
        //             |
        //           +----+
        //           | b  |
        //           +----+
    }

    public function testValid()
    {
        $places = ['a', 'b', 'c'];
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t2', 'a', 'c');
        $definition = new Definition($places, $transitions);

        (new StateMachineValidator())->validate($definition, 'foo');

        // the test simply ensures that the validation does not fail (i.e. it does not throw any exceptions)
        $this->addToAssertionCount(1);

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
