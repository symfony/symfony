<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stopwatch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Section;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * StopwatchTest.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @group time-sensitive
 */
class StopwatchTest extends TestCase
{
    private const DELTA = 20;

    public function testStart()
    {
        $stopwatch = new Stopwatch();
        $event = $stopwatch->start('foo', 'cat');

        $this->assertInstanceOf(StopwatchEvent::class, $event);
        $this->assertEquals('cat', $event->getCategory());
        $this->assertSame($event, $stopwatch->getEvent('foo'));
    }

    public function testStartWithoutCategory()
    {
        $stopwatch = new Stopwatch();
        $stopwatchEvent = $stopwatch->start('bar');
        $this->assertSame('default', $stopwatchEvent->getCategory());
        $this->assertSame($stopwatchEvent, $stopwatch->getEvent('bar'));
    }

    public function testIsStarted()
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('foo', 'cat');

        $this->assertTrue($stopwatch->isStarted('foo'));
    }

    public function testIsNotStarted()
    {
        $stopwatch = new Stopwatch();

        $this->assertFalse($stopwatch->isStarted('foo'));
    }

    public function testIsNotStartedEvent()
    {
        $stopwatch = new Stopwatch();

        $sections = new \ReflectionProperty(Stopwatch::class, 'sections');
        $section = $sections->getValue($stopwatch);

        $events = new \ReflectionProperty(Section::class, 'events');

        $events->setValue(end($section), ['foo' => new StopwatchEvent(microtime(true) * 1000)]);

        $this->assertFalse($stopwatch->isStarted('foo'));
    }

    public function testStop()
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('foo', 'cat');
        usleep(200000);
        $event = $stopwatch->stop('foo');

        $this->assertInstanceOf(StopwatchEvent::class, $event);
        $this->assertEqualsWithDelta(200, $event->getDuration(), self::DELTA);
    }

    public function testUnknownEvent()
    {
        $this->expectException(\LogicException::class);
        $stopwatch = new Stopwatch();
        $stopwatch->getEvent('foo');
    }

    public function testStopWithoutStart()
    {
        $this->expectException(\LogicException::class);
        $stopwatch = new Stopwatch();
        $stopwatch->stop('foo');
    }

    public function testMorePrecision()
    {
        $stopwatch = new Stopwatch(true);

        $stopwatch->start('foo');
        $event = $stopwatch->stop('foo');

        $this->assertIsFloat($event->getStartTime());
        $this->assertIsFloat($event->getEndTime());
        $this->assertIsFloat($event->getDuration());
    }

    public function testSection()
    {
        $stopwatch = new Stopwatch();

        $stopwatch->openSection();
        $stopwatch->start('foo', 'cat');
        $stopwatch->stop('foo');
        $stopwatch->start('bar', 'cat');
        $stopwatch->stop('bar');
        $stopwatch->stopSection('1');

        $stopwatch->openSection();
        $stopwatch->start('foobar', 'cat');
        $stopwatch->stop('foobar');
        $stopwatch->stopSection('2');

        $stopwatch->openSection();
        $stopwatch->start('foobar', 'cat');
        $stopwatch->stop('foobar');
        $stopwatch->stopSection('0');

        // the section is an event by itself
        $this->assertCount(3, $stopwatch->getSectionEvents('1'));
        $this->assertCount(2, $stopwatch->getSectionEvents('2'));
        $this->assertCount(2, $stopwatch->getSectionEvents('0'));
    }

    public function testReopenASection()
    {
        $stopwatch = new Stopwatch();

        $stopwatch->openSection();
        $stopwatch->start('foo', 'cat');
        $stopwatch->stopSection('section');

        $stopwatch->openSection('section');
        $stopwatch->start('bar', 'cat');
        $stopwatch->stopSection('section');

        $events = $stopwatch->getSectionEvents('section');

        $this->assertCount(3, $events);
        $this->assertCount(2, $events['__section__']->getPeriods());
    }

    public function testReopenANewSectionShouldThrowAnException()
    {
        $this->expectException(\LogicException::class);
        $stopwatch = new Stopwatch();
        $stopwatch->openSection('section');
    }

    public function testReset()
    {
        $stopwatch = new Stopwatch();

        $stopwatch->openSection();
        $stopwatch->start('foo', 'cat');

        $stopwatch->reset();

        $this->assertEquals(new Stopwatch(), $stopwatch);
    }
}
