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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\Transition;

class TransitionTest extends TestCase
{
    public static function provideConstructorTests(): iterable
    {
        yield 'plain strings' => ['a', 'b'];
        yield 'array of strings' => [['a'], ['b']];
        yield 'array of arcs' => [[new Arc('a', 1)], [new Arc('b', 1)]];
    }

    #[DataProvider('provideConstructorTests')]
    public function testConstructor(mixed $froms, mixed $tos)
    {
        $transition = new Transition('name', $froms, $tos);

        $this->assertSame('name', $transition->getName());
        $this->assertCount(1, $transition->getFroms(true));
        $this->assertSame('a', $transition->getFroms(true)[0]->place);
        $this->assertSame(1, $transition->getFroms(true)[0]->weight);
        $this->assertCount(1, $transition->getTos(true));
        $this->assertSame('b', $transition->getTos(true)[0]->place);
        $this->assertSame(1, $transition->getTos(true)[0]->weight);
    }

    public function testConstructorWithInvalidData()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('The type of arc is invalid. Expected string or Arc, got "bool".');

        new Transition('name', [true], ['a']);
    }

    public function testLegacyGetter()
    {
        $transition = new Transition('name', 'a', 'b');

        $this->assertSame(['a'], $transition->getFroms());
        $this->assertSame(['b'], $transition->getTos());
    }
}
