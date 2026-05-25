<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Event\TickEvent;

class TickEventTest extends TestCase
{
    public function testDeltaTimeDefaultsToZero()
    {
        $event = new TickEvent();

        $this->assertSame(0.0, $event->getDeltaTime());
    }

    public function testDeltaTimeCanBeProvided()
    {
        $event = new TickEvent(0.125);

        $this->assertSame(0.125, $event->getDeltaTime());
    }

    public function testHasNoBusyHintByDefault()
    {
        $event = new TickEvent();

        $this->assertFalse($event->hasBusyHint());
        $this->assertFalse($event->isBusy());
    }

    public function testSetBusyTrueMarksBusy()
    {
        $event = new TickEvent();
        $event->setBusy();

        $this->assertTrue($event->hasBusyHint());
        $this->assertTrue($event->isBusy());
    }

    public function testSetBusyFalseMarksIdle()
    {
        $event = new TickEvent();
        $event->setBusy(false);

        $this->assertTrue($event->hasBusyHint());
        $this->assertFalse($event->isBusy());
    }
}
