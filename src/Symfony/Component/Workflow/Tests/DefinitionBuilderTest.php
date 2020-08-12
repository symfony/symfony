<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowEvents;

class DefinitionBuilderTest extends TestCase
{
    public function testSetInitialPlaces()
    {
        $builder = new DefinitionBuilder(['a', 'b']);
        $builder->setInitialPlaces('b');
        $definition = $builder->build();

        $this->assertEquals(['b'], $definition->getInitialPlaces());
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

    public function testSetMetadataStore()
    {
        $builder = new DefinitionBuilder(['a']);
        $metadataStore = new InMemoryMetadataStore();
        $builder->setMetadataStore($metadataStore);
        $definition = $builder->build();

        $this->assertSame($metadataStore, $definition->getMetadataStore());
    }

    public function testCheckDefaultDispatchEvents()
    {
        $builder = new DefinitionBuilder(['a']);
        $definition = $builder->build();

        $this->assertSame(WorkflowEvents::getDefaultDispatchedEvents(), $definition->getDispatchedEvents());
    }

    public function testSetEmptyDispatchEvents()
    {
        $builder = new DefinitionBuilder(['a']);
        $builder->setDispatchEvents([]);
        $definition = $builder->build();

        $this->assertSame([], $definition->getDispatchedEvents());
    }

    public function testSetSpecificDispatchEvents()
    {
        $events = [WorkflowEvents::ENTERED, WorkflowEvents::COMPLETED];

        $builder = new DefinitionBuilder(['a']);
        $builder->setDispatchEvents($events);
        $definition = $builder->build();

        $this->assertSame($events, $definition->getDispatchedEvents());
    }
}
