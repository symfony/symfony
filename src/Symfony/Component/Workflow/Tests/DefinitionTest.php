<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowEvents;

class DefinitionTest extends TestCase
{
    public function testAddPlaces()
    {
        $places = range('a', 'e');
        $definition = new Definition($places, []);

        $this->assertCount(5, $definition->getPlaces());

        $this->assertEquals(['a'], $definition->getInitialPlaces());
    }

    public function testSetInitialPlace()
    {
        $places = range('a', 'e');
        $definition = new Definition($places, [], $places[3]);

        $this->assertEquals([$places[3]], $definition->getInitialPlaces());
    }

    public function testSetInitialPlaces()
    {
        $places = range('a', 'e');
        $definition = new Definition($places, [], ['a', 'e']);

        $this->assertEquals(['a', 'e'], $definition->getInitialPlaces());
    }

    public function testSetInitialPlaceAndPlaceIsNotDefined()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\LogicException');
        $this->expectExceptionMessage('Place "d" cannot be the initial place as it does not exist.');
        new Definition([], [], 'd');
    }

    public function testAddTransition()
    {
        $places = range('a', 'b');

        $transition = new Transition('name', $places[0], $places[1]);
        $definition = new Definition($places, [$transition]);

        $this->assertCount(1, $definition->getTransitions());
        $this->assertSame($transition, $definition->getTransitions()[0]);
    }

    public function testAddTransitionAndFromPlaceIsNotDefined()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\LogicException');
        $this->expectExceptionMessage('Place "c" referenced in transition "name" does not exist.');
        $places = range('a', 'b');

        new Definition($places, [new Transition('name', 'c', $places[1])]);
    }

    public function testAddTransitionAndToPlaceIsNotDefined()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\LogicException');
        $this->expectExceptionMessage('Place "c" referenced in transition "name" does not exist.');
        $places = range('a', 'b');

        new Definition($places, [new Transition('name', $places[0], 'c')]);
    }

    public function testSetDefaultDispatchEvents()
    {
        $places = range('a', 'b');
        $definition = new Definition($places, [], null, null, null);

        $this->assertSame(WorkflowEvents::getDefaultDispatchEvents(), $definition->getDispatchEvents());
    }

    public function testSetEmptyDispatchEvents()
    {
        $places = range('a', 'b');
        $definition = new Definition($places, [], null, null, []);

        $this->assertEmpty($definition->getDispatchEvents());
    }

    public function testSetSpecificDispatchEvents()
    {
        $events = [WorkflowEvents::ENTERED, WorkflowEvents::COMPLETED];

        $places = range('a', 'b');
        $definition = new Definition($places, [], null, null, $events);

        $this->assertSame($events, $definition->getDispatchEvents());
    }
}
