<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Tests\fixtures\FooEnum;
use Symfony\Component\Workflow\Transition;

class DefinitionTest extends TestCase
{
    public function testAddPlacesAsString()
    {
        $places = range('a', 'e');
        $definition = new Definition($places, []);

        $this->assertCount(5, $definition->getPlaces());

        $this->assertEquals(['a'], $definition->getInitialPlaces());
    }

    /**
     * @requires PHP 8.1
     */
    public function testAddPlacesAsEnum()
    {
        $places = FooEnum::cases();
        $definition = new Definition($places, []);

        $this->assertCount(\count(FooEnum::cases()), $definition->getPlaces());

        $this->assertEquals([FooEnum::Bar], $definition->getInitialPlaces());
    }

    public function testSetInitialPlaceAsString()
    {
        $places = range('a', 'e');
        $definition = new Definition($places, [], $places[3]);

        $this->assertEquals([$places[3]], $definition->getInitialPlaces());
    }

    /**
     * @requires PHP 8.1
     */
    public function testSetInitialPlaceAsEnum()
    {
        $places = FooEnum::cases();
        $definition = new Definition($places, [], FooEnum::Baz);

        $this->assertEquals([FooEnum::Baz], $definition->getInitialPlaces());
    }

    public function testSetInitialPlacesAsString()
    {
        $places = range('a', 'e');
        $definition = new Definition($places, [], ['a', 'e']);

        $this->assertEquals(['a', 'e'], $definition->getInitialPlaces());
    }

    /**
     * @requires PHP 8.1
     */
    public function testSetInitialPlacesAsEnum()
    {
        $places = FooEnum::cases();
        $definition = new Definition($places, [], [FooEnum::Bar, FooEnum::Qux]);

        $this->assertEquals([FooEnum::Bar, FooEnum::Qux], $definition->getInitialPlaces());
    }

    public function testSetInitialPlaceAsStringAndPlaceIsNotDefined()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Place "d" cannot be the initial place as it does not exist.');
        new Definition([], [], 'd');
    }

    /**
     * @requires PHP 8.1
     */
    public function testSetInitialPlaceAsEnumAndPlaceIsNotDefined()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Place "Symfony\Component\Workflow\Tests\fixtures\FooEnum::Bar" cannot be the initial place as it does not exist.');
        new Definition([], [], FooEnum::Bar);
    }

    public function testAddTransitionWithStringPlaces()
    {
        $places = range('a', 'b');

        $transition = new Transition('name', $places[0], $places[1]);
        $definition = new Definition($places, [$transition]);

        $this->assertCount(1, $definition->getTransitions());
        $this->assertSame($transition, $definition->getTransitions()[0]);
    }

    /**
     * @requires PHP 8.1
     */
    public function testAddTransitionWithEnumPlaces()
    {
        $places = FooEnum::cases();

        $transition = new Transition('name', $places[0], $places[1]);
        $definition = new Definition($places, [$transition]);

        $this->assertCount(1, $definition->getTransitions());
        $this->assertSame($transition, $definition->getTransitions()[0]);
    }

    public function testAddTransitionAndFromPlaceAsStringIsNotDefined()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Place "c" referenced in transition "name" does not exist.');
        $places = range('a', 'b');

        new Definition($places, [new Transition('name', 'c', $places[1])]);
    }

    /**
     * @requires PHP 8.1
     */
    public function testAddTransitionAndFromPlaceAsEnumIsNotDefined()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Place "Symfony\Component\Workflow\Tests\fixtures\FooEnum::Qux" referenced in transition "name" does not exist.');
        $places = [FooEnum::Bar, FooEnum::Baz];

        new Definition($places, [new Transition('name', FooEnum::Qux, $places[1])]);
    }

    public function testAddTransitionAndToPlaceAsStringIsNotDefined()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Place "c" referenced in transition "name" does not exist.');
        $places = range('a', 'b');

        new Definition($places, [new Transition('name', $places[0], 'c')]);
    }

    /**
     * @requires PHP 8.1
     */
    public function testAddTransitionAndToPlaceAsEnumIsNotDefined()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Place "Symfony\Component\Workflow\Tests\fixtures\FooEnum::Qux" referenced in transition "name" does not exist.');
        $places = [FooEnum::Bar, FooEnum::Baz];

        new Definition($places, [new Transition('name', $places[0], FooEnum::Qux)]);
    }
}
