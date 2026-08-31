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

        // Due at 0.50 but the loop only came back at 0.95, so this run is
        // late; the period it belongs to still ends at 1.00. Polling away from
        // the period boundaries keeps the counts below independent of the
        // offset between $start and the clock reading made by schedule().
        $scheduler->runDue($start + 0.95);
        $this->assertSame(1, $calls);

        // Being served late does not push the following period back.
        $scheduler->runDue($start + 1.20);
        $this->assertSame(2, $calls);

        $scheduler->runDue($start + 1.60);
        $this->assertSame(3, $calls);
    }

    public function testRunDueDoesNotDriftBehindTheInterval()
    {
        $scheduler = new TickScheduler();
        $calls = 0;
        $start = microtime(true);

        $scheduler->schedule(static function () use (&$calls): void {
            ++$calls;
        }, 0.100);

        // A loop polling every 30 ms cannot land on the 100 ms boundary, but
        // the rate it serves must still be one call per 100 ms. The window ends
        // at 6.06 rather than on a period boundary so that the last period is
        // served whichever side of $start the clock reading made by schedule()
        // fell on.
        for ($i = 1; $i <= 202; ++$i) {
            $scheduler->runDue($start + $i * 0.030);
        }

        $this->assertSame(60, $calls);
    }

    public function testRunDueSkipsPeriodsMissedWhileTheLoopWasStalled()
    {
        $scheduler = new TickScheduler();
        $calls = 0;
        $start = microtime(true);

        $scheduler->schedule(static function () use (&$calls): void {
            ++$calls;
        }, 0.100);

        // Nine periods went by unserved while the loop was away.
        $scheduler->runDue($start + 0.95);
        $this->assertSame(1, $calls);

        // The loop is back and polling every 10 ms. The missed periods are
        // dropped, so the next 100 ms hold one more call rather than replaying
        // the backlog one poll at a time.
        for ($i = 1; $i <= 10; ++$i) {
            $scheduler->runDue($start + 0.95 + $i * 0.010);
        }

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
