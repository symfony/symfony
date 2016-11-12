<?php

namespace Symfony\Component\Workflow\Tests;

use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Transition;

class DefinitionBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidArgumentException
     */
    public function testAddPlaceInvalidName()
    {
        $builder = new DefinitionBuilder(array('a"', 'b'));
    }

    public function testSetInitialPlace()
    {
        $builder = new DefinitionBuilder(array('a', 'b'));
        $builder->setInitialPlace('b');
        $definition = $builder->build();

        $this->assertEquals('b', $definition->getInitialPlace());
    }

    public function testAddTransition()
    {
        $name0 = 'name0';
        $name1 = 'name1';
        $places = range('a', 'b');

        $transition0 = array($name0, $places[0], $places[1]);
        $builder = new DefinitionBuilder($places, array($transition0));

        $builder->addTransition($name1, $places[0], $places[1]);

        $definition = $builder->build();

        $this->assertCount(2, $definition->getTransitions());
        $this->assertEquals(new Transition($name0, $places[0], $places[1]), $definition->getTransitions()[0]);
        $this->assertEquals(new Transition($name1, $places[0], $places[1]), $definition->getTransitions()[1]);
    }

    public function testAddPlace()
    {
        $builder = new DefinitionBuilder(array('a'), array());
        $builder->addPlace('b');

        $definition = $builder->build();

        $this->assertCount(2, $definition->getPlaces());
        $this->assertEquals('a', $definition->getPlaces()['a']);
        $this->assertEquals('b', $definition->getPlaces()['b']);
    }
}
