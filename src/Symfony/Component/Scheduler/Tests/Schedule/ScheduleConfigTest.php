<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Schedule;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Schedule\ScheduleConfig;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class ScheduleConfigTest extends TestCase
{
    public function testEmpty()
    {
        $config = new ScheduleConfig();

        $this->assertSame([], $config->getSchedule());
    }

    public function testAdd()
    {
        $config = new ScheduleConfig();

        $config->add($t1 = $this->createMock(TriggerInterface::class), $o1 = (object) ['name' => 'first']);
        $config->add($t2 = $this->createMock(TriggerInterface::class), $o2 = (object) ['name' => 'second']);

        $expected = [
            [$t1, $o1],
            [$t2, $o2],
        ];

        $this->assertSame($expected, $config->getSchedule());
    }

    public function testFromIterator()
    {
        $expected = [
            [$this->createMock(TriggerInterface::class), (object) ['name' => 'first']],
            [$this->createMock(TriggerInterface::class), (object) ['name' => 'second']],
        ];

        $config = new ScheduleConfig(new \ArrayObject($expected));

        $this->assertSame($expected, $config->getSchedule());
    }

    public function testFromBadIterator()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of type Symfony\Component\Scheduler\Trigger\TriggerInterface');

        new ScheduleConfig([new \ArrayObject(['wrong'])]);
    }
}
