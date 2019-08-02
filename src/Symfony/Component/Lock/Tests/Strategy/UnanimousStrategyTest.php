<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Strategy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Strategy\UnanimousStrategy;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class UnanimousStrategyTest extends TestCase
{
    /** @var UnanimousStrategy */
    private $strategy;

    protected function setUp()
    {
        $this->strategy = new UnanimousStrategy();
    }

    public function provideMetResults()
    {
        // success, failure, total, isMet
        yield [3, 0, 3, true];
        yield [2, 1, 3, false];
        yield [2, 0, 3, false];
        yield [1, 2, 3, false];
        yield [1, 1, 3, false];
        yield [1, 0, 3, false];
        yield [0, 3, 3, false];
        yield [0, 2, 3, false];
        yield [0, 1, 3, false];
        yield [0, 0, 3, false];

        yield [2, 0, 2, true];
        yield [1, 1, 2, false];
        yield [1, 0, 2, false];
        yield [0, 2, 2, false];
        yield [0, 1, 2, false];
        yield [0, 0, 2, false];
    }

    public function provideIndeterminate()
    {
        // success, failure, total, canBeMet
        yield [3, 0, 3, true];
        yield [2, 1, 3, false];
        yield [2, 0, 3, true];
        yield [1, 2, 3, false];
        yield [1, 1, 3, false];
        yield [1, 0, 3, true];
        yield [0, 3, 3, false];
        yield [0, 2, 3, false];
        yield [0, 1, 3, false];
        yield [0, 0, 3, true];

        yield [2, 0, 2, true];
        yield [1, 1, 2, false];
        yield [1, 0, 2, true];
        yield [0, 2, 2, false];
        yield [0, 1, 2, false];
        yield [0, 0, 2, true];
    }

    /**
     * @dataProvider provideMetResults
     */
    public function testMet($success, $failure, $total, $isMet)
    {
        $this->assertSame($isMet, $this->strategy->isMet($success, $total));
    }

    /**
     * @dataProvider provideIndeterminate
     */
    public function testCanBeMet($success, $failure, $total, $isMet)
    {
        $this->assertSame($isMet, $this->strategy->canBeMet($failure, $total));
    }
}
