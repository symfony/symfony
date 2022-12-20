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

        self::assertTrue($marking->has('a'));
        self::assertFalse($marking->has('b'));
        self::assertSame(['a' => 1], $marking->getPlaces());

        $marking->mark('b');

        self::assertTrue($marking->has('a'));
        self::assertTrue($marking->has('b'));
        self::assertSame(['a' => 1, 'b' => 1], $marking->getPlaces());

        $marking->unmark('a');

        self::assertFalse($marking->has('a'));
        self::assertTrue($marking->has('b'));
        self::assertSame(['b' => 1], $marking->getPlaces());

        $marking->unmark('b');

        self::assertFalse($marking->has('a'));
        self::assertFalse($marking->has('b'));
        self::assertSame([], $marking->getPlaces());
    }
}
