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

        self::assertCount(5, $definition->getPlaces());

        self::assertEquals(['a'], $definition->getInitialPlaces());
    }

    public function testSetInitialPlace()
    {
        $places = range('a', 'e');
        $definition = new Definition($places, [], $places[3]);

        self::assertEquals([$places[3]], $definition->getInitialPlaces());
    }

    public function testSetInitialPlaces()
    {
        $places = range('a', 'e');
        $definition = new Definition($places, [], ['a', 'e']);

        self::assertEquals(['a', 'e'], $definition->getInitialPlaces());
    }

    public function testSetInitialPlaceAndPlaceIsNotDefined()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('Place "d" cannot be the initial place as it does not exist.');
        new Definition([], [], 'd');
    }

    public function testAddTransition()
    {
        $places = range('a', 'b');

        $transition = new Transition('name', $places[0], $places[1]);
        $definition = new Definition($places, [$transition]);

        self::assertCount(1, $definition->getTransitions());
        self::assertSame($transition, $definition->getTransitions()[0]);
    }

    public function testAddTransitionAndFromPlaceIsNotDefined()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('Place "c" referenced in transition "name" does not exist.');
        $places = range('a', 'b');

        new Definition($places, [new Transition('name', 'c', $places[1])]);
    }

    public function testAddTransitionAndToPlaceIsNotDefined()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('Place "c" referenced in transition "name" does not exist.');
        $places = range('a', 'b');

        new Definition($places, [new Transition('name', $places[0], 'c')]);
    }
}
