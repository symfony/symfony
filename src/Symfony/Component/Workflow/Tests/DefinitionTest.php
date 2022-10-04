<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Transition;

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
        $this->expectException(LogicException::class);
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
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Place "c" referenced in transition "name" does not exist.');
        $places = range('a', 'b');

        new Definition($places, [new Transition('name', 'c', $places[1])]);
    }

    public function testAddTransitionAndToPlaceIsNotDefined()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Place "c" referenced in transition "name" does not exist.');
        $places = range('a', 'b');

        new Definition($places, [new Transition('name', $places[0], 'c')]);
    }
}
