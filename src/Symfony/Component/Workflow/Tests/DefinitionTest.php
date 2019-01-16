<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Transition;

class DefinitionTest extends TestCase
{
    public function testAddPlaces()
    {
        $places = range('a', 'e');
        $definition = new Definition($places, []);

        $this->assertCount(5, $definition->getPlaces());

        $this->assertEquals('a', $definition->getInitialPlace());
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidArgumentException
     */
    public function testAddPlacesInvalidArgument()
    {
        $places = ['a"', 'e"'];
        $definition = new Definition($places, []);
    }

    public function testSetInitialPlace()
    {
        $places = range('a', 'e');
        $definition = new Definition($places, [], $places[3]);

        $this->assertEquals($places[3], $definition->getInitialPlace());
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\LogicException
     * @expectedExceptionMessage Place "d" cannot be the initial place as it does not exist.
     */
    public function testSetInitialPlaceAndPlaceIsNotDefined()
    {
        $definition = new Definition([], [], 'd');
    }

    public function testAddTransition()
    {
        $places = range('a', 'b');

        $transition = new Transition('name', $places[0], $places[1]);
        $definition = new Definition($places, [$transition]);

        $this->assertCount(1, $definition->getTransitions());
        $this->assertSame($transition, $definition->getTransitions()[0]);
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\LogicException
     * @expectedExceptionMessage Place "c" referenced in transition "name" does not exist.
     */
    public function testAddTransitionAndFromPlaceIsNotDefined()
    {
        $places = range('a', 'b');

        new Definition($places, [new Transition('name', 'c', $places[1])]);
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\LogicException
     * @expectedExceptionMessage Place "c" referenced in transition "name" does not exist.
     */
    public function testAddTransitionAndToPlaceIsNotDefined()
    {
        $places = range('a', 'b');

        new Definition($places, [new Transition('name', $places[0], 'c')]);
    }
}
