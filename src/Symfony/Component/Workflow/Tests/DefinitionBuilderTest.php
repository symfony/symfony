<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Transition;

class DefinitionBuilderTest extends TestCase
{
    public function testAddPlaceInvalidName()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\InvalidArgumentException');
        new DefinitionBuilder(['a"', 'b']);
    }

    public function testSetInitialPlace()
    {
        $builder = new DefinitionBuilder(['a', 'b']);
        $builder->setInitialPlace('b');
        $definition = $builder->build();

        $this->assertEquals('b', $definition->getInitialPlace());
    }

    public function testAddTransition()
    {
        $places = range('a', 'b');

        $transition0 = new Transition('name0', $places[0], $places[1]);
        $transition1 = new Transition('name1', $places[0], $places[1]);
        $builder = new DefinitionBuilder($places, [$transition0]);
        $builder->addTransition($transition1);

        $definition = $builder->build();

        $this->assertCount(2, $definition->getTransitions());
        $this->assertSame($transition0, $definition->getTransitions()[0]);
        $this->assertSame($transition1, $definition->getTransitions()[1]);
    }

    public function testAddPlace()
    {
        $builder = new DefinitionBuilder(['a'], []);
        $builder->addPlace('b');

        $definition = $builder->build();

        $this->assertCount(2, $definition->getPlaces());
        $this->assertEquals('a', $definition->getPlaces()['a']);
        $this->assertEquals('b', $definition->getPlaces()['b']);
    }
}
