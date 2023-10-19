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
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class JitterTriggerTest extends TestCase
{
    public function testCanAddJitter()
    {
        $time = new \DateTimeImmutable();
        $inner = $this->createMock(TriggerInterface::class);
        $inner->method('getNextRunDate')->willReturn($time);

        $trigger = new JitterTrigger($inner);

        $values = array_map(
            fn () => (int) $trigger->getNextRunDate($time)?->getTimestamp(),
            array_fill(0, 100, null)
        );

        foreach ($values as $value) {
            $this->assertGreaterThanOrEqual($time->getTimestamp(), $value);
            $this->assertLessThanOrEqual($time->getTimestamp() + 60, $value);
        }

        $values = array_unique($values);

        $this->assertGreaterThan(1, \count($values));
    }
}
