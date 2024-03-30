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
use Symfony\Component\Workflow\Marking;

class MarkingTest extends TestCase
{
    public function testMarking()
    {
        $marking = new Marking(['a' => 1]);

        $this->assertTrue($marking->has('a'));
        $this->assertFalse($marking->has('b'));
        $this->assertPlaces(['a' => 1], $marking);

        $marking->mark('b');

        $this->assertTrue($marking->has('a'));
        $this->assertTrue($marking->has('b'));
        $this->assertPlaces(['a' => 1, 'b' => 1], $marking);

        $marking->unmark('a');

        $this->assertFalse($marking->has('a'));
        $this->assertTrue($marking->has('b'));
        $this->assertPlaces(['b' => 1], $marking);

        $marking->unmark('b');

        $this->assertFalse($marking->has('a'));
        $this->assertFalse($marking->has('b'));
        $this->assertPlaces([], $marking);

        $marking->mark('a');
        $this->assertPlaces(['a' => 1], $marking);

        $marking->mark('a');
        $this->assertPlaces(['a' => 2], $marking);

        $marking->unmark('a');
        $this->assertPlaces(['a' => 1], $marking);

        $marking->unmark('a');
        $this->assertPlaces([], $marking);
    }

    public function testGuardNotMarked()
    {
        $marking = new Marking([]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The place "a" is not marked.');
        $marking->unmark('a');
    }

    public function testUnmarkGuardResultTokenCountIsNotNegative()
    {
        $marking = new Marking(['a' => 1]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The place "a" could not contain a negative token number: "1" (initial) - "2" (nbToken) = "-1".');
        $marking->unmark('a', 2);
    }

    public function testUnmarkGuardNbTokenIsGreaterThanZero()
    {
        $marking = new Marking(['a' => 1]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The number of tokens must be greater than 0, "0" given.');
        $marking->unmark('a', 0);
    }

    private function assertPlaces(array $expected, Marking $marking)
    {
        $places = $marking->getPlaces();
        ksort($places);
        $this->assertSame($expected, $places);
    }
}
