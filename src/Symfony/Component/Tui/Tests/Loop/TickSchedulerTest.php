<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Loop;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Loop\TickScheduler;

class TickSchedulerTest extends TestCase
{
    public function testScheduleRejectsNonPositiveIntervals()
    {
        $scheduler = new TickScheduler();

        $this->expectException(InvalidArgumentException::class);
        $scheduler->schedule(static function (): void {}, 0.0);
    }

    public function testRunDueExecutesAndReschedulesCallbacks()
    {
        $scheduler = new TickScheduler();
        $calls = 0;
        $start = microtime(true);

        $scheduler->schedule(static function () use (&$calls): void {
            ++$calls;
        }, 0.5);

        $scheduler->runDue($start + 0.10);
        $this->assertSame(0, $calls);

        $scheduler->runDue($start + 1.00);
        $this->assertSame(1, $calls);

        $scheduler->runDue($start + 1.20);
        $this->assertSame(1, $calls);

        $scheduler->runDue($start + 1.60);
        $this->assertSame(2, $calls);
    }

    public function testCancelPreventsFutureExecution()
    {
        $scheduler = new TickScheduler();
        $calls = 0;
        $start = microtime(true);

        $id = $scheduler->schedule(static function () use (&$calls): void {
            ++$calls;
        }, 0.01);

        $scheduler->cancel($id);
        $scheduler->runDue($start + 1.0);

        $this->assertSame(0, $calls);
    }

    public function testGetNextDelayReturnsNullWhenNoIntervals()
    {
        $scheduler = new TickScheduler();

        $this->assertNull($scheduler->getNextDelay());
    }

    public function testGetNextDelayReturnsSmallestDelay()
    {
        $scheduler = new TickScheduler();
        $start = microtime(true);

        $scheduler->schedule(static function (): void {}, 0.5);
        $scheduler->schedule(static function (): void {}, 1.0);

        $delay = $scheduler->getNextDelay($start + 0.25);

        $this->assertNotNull($delay);
        $this->assertGreaterThanOrEqual(0.001, $delay);
        $this->assertLessThan(0.5, $delay);
    }
}
