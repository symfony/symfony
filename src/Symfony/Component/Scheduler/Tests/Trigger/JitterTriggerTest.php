<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Trigger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Trigger\JitterTrigger;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class JitterTriggerTest extends TestCase
{
    public function testCanAddJitter()
    {
        $time = new \DateTimeImmutable();
        $inner = $this->createStub(TriggerInterface::class);
        $inner->method('getNextRunDate')->willReturn($time);

        $trigger = new JitterTrigger($inner);

        $values = array_map(
            static fn () => (int) $trigger->getNextRunDate($time)?->getTimestamp(),
            array_fill(0, 100, null)
        );

        foreach ($values as $value) {
            $this->assertGreaterThanOrEqual($time->getTimestamp(), $value);
            $this->assertLessThanOrEqual($time->getTimestamp() + 60, $value);
        }

        $values = array_unique($values);

        $this->assertGreaterThan(1, \count($values));
    }

    public function testDoesNotSkipRunsWithJitter()
    {
        $from = new \DateTimeImmutable('2026-07-07 16:30:00');
        $inner = new PeriodicalTrigger(10, $from);
        $trigger = new JitterTrigger($inner, 15);

        // The first run at 16:30:00 was executed at 16:30:12 due to a 12s jitter.
        $run = $from->modify('+12 seconds');

        $nextRun = $trigger->getNextRunDate($run);

        $this->assertNotNull($nextRun);
        // The slot at 16:30:10 is already ran, so the next scheduled run is 16:30:20 + a random jitter up to 15s.
        $this->assertGreaterThanOrEqual($from->modify('+20 seconds')->getTimestamp(), $nextRun->getTimestamp());
        $this->assertLessThanOrEqual($from->modify('+35 seconds')->getTimestamp(), $nextRun->getTimestamp());
    }
}
