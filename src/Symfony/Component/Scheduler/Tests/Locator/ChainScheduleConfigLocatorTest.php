<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Locator;

use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Scheduler\Locator\ChainScheduleConfigLocator;
use Symfony\Component\Scheduler\Locator\ScheduleConfigLocatorInterface;
use Symfony\Component\Scheduler\Schedule\ScheduleConfig;

class ChainScheduleConfigLocatorTest extends TestCase
{
    public function testExists()
    {
        $schedule = new ScheduleConfig();

        $empty = $this->createMock(ScheduleConfigLocatorInterface::class);
        $empty->expects($this->once())->method('has')->with('exists')->willReturn(false);
        $empty->expects($this->never())->method('get');

        $full = $this->createMock(ScheduleConfigLocatorInterface::class);
        $full->expects($this->once())->method('has')->with('exists')->willReturn(true);
        $full->expects($this->once())->method('get')->with('exists')->willReturn($schedule);

        $locator = new ChainScheduleConfigLocator([$empty, $full]);

        $this->assertTrue($locator->has('exists'));
        $this->assertSame($schedule, $locator->get('exists'));
    }

    public function testNonExists()
    {
        $locator = new ChainScheduleConfigLocator([$this->createMock(ScheduleConfigLocatorInterface::class)]);

        $this->assertFalse($locator->has('non-exists'));
        $this->expectException(NotFoundExceptionInterface::class);

        $locator->get('non-exists');
    }
}
