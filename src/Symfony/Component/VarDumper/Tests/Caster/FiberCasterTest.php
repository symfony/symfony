<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class FiberCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testCastFiberNotStarted()
    {
        $fiber = new \Fiber(static fn () => true);

        $expected = <<<EODUMP
Fiber {
  status: "not started"
}
EODUMP;

        $this->assertDumpEquals($expected, $fiber);
    }

    public function testCastFiberTerminated()
    {
        $fiber = new \Fiber(static fn () => true);
        $fiber->start();

        $expected = <<<EODUMP
Fiber {
  status: "terminated"
}
EODUMP;

        $this->assertDumpEquals($expected, $fiber);
    }

    public function testCastFiberSuspended()
    {
        $fiber = new \Fiber(\Fiber::suspend(...));
        $fiber->start();

        $expected = <<<EODUMP
Fiber {
  status: "suspended"
}
EODUMP;

        $this->assertDumpEquals($expected, $fiber);
    }

    public function testCastFiberRunning()
    {
        $fiber = new \Fiber(function () {
            $expected = <<<EODUMP
Fiber {
  status: "running"
}
EODUMP;

            $this->assertDumpEquals($expected, \Fiber::getCurrent());
        });

        $fiber->start();
    }
}
