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
use Symfony\Component\Scheduler\Trigger\ExcludeTimeTrigger;
use Symfony\Component\Scheduler\Trigger\JitterTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class AbstractDecoratedTriggerTest extends TestCase
{
    public function testCanGetInnerTrigger()
    {
        $trigger = new JitterTrigger($inner = $this->createMock(TriggerInterface::class));

        $this->assertSame($inner, $trigger->inner());
        $this->assertSame([$trigger], iterator_to_array($trigger->decorators()));
    }

    public function testCanGetNestedInnerTrigger()
    {
        $trigger = new ExcludeTimeTrigger(
            $jitter = new JitterTrigger($inner = $this->createMock(TriggerInterface::class)),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );

        $this->assertSame($inner, $trigger->inner());
        $this->assertSame([$trigger, $jitter], iterator_to_array($trigger->decorators()));
    }
}
