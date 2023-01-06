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
        $this->assertSame(['a' => 1], $marking->getPlaces());

        $marking->mark('b');

        $this->assertTrue($marking->has('a'));
        $this->assertTrue($marking->has('b'));
        $this->assertSame(['a' => 1, 'b' => 1], $marking->getPlaces());

        $marking->unmark('a');

        $this->assertFalse($marking->has('a'));
        $this->assertTrue($marking->has('b'));
        $this->assertSame(['b' => 1], $marking->getPlaces());

        $marking->unmark('b');

        $this->assertFalse($marking->has('a'));
        $this->assertFalse($marking->has('b'));
        $this->assertSame([], $marking->getPlaces());
    }
}
