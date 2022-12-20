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

        self::assertInstanceOf(StopwatchEvent::class, $event);
        self::assertEquals('cat', $event->getCategory());
        self::assertSame($event, $stopwatch->getEvent('foo'));
    }

    public function testStartWithoutCategory()
    {
        $stopwatch = new Stopwatch();
        $stopwatchEvent = $stopwatch->start('bar');
        self::assertSame('default', $stopwatchEvent->getCategory());
        self::assertSame($stopwatchEvent, $stopwatch->getEvent('bar'));
    }

    public function testIsStarted()
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('foo', 'cat');

        self::assertTrue($stopwatch->isStarted('foo'));
    }

    public function testIsNotStarted()
    {
        $stopwatch = new Stopwatch();

        self::assertFalse($stopwatch->isStarted('foo'));
    }

    public function testIsNotStartedEvent()
    {
        $stopwatch = new Stopwatch();

        $sections = new \ReflectionProperty(Stopwatch::class, 'sections');
        $sections->setAccessible(true);
        $section = $sections->getValue($stopwatch);

        $events = new \ReflectionProperty(Section::class, 'events');
        $events->setAccessible(true);

        $events->setValue(end($section), ['foo' => new StopwatchEvent(microtime(true) * 1000)]);

        self::assertFalse($stopwatch->isStarted('foo'));
    }

    public function testStop()
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('foo', 'cat');
        usleep(200000);
        $event = $stopwatch->stop('foo');

        self::assertInstanceOf(StopwatchEvent::class, $event);
        self::assertEqualsWithDelta(200, $event->getDuration(), self::DELTA);
    }

    public function testUnknownEvent()
    {
        self::expectException(\LogicException::class);
        $stopwatch = new Stopwatch();
        $stopwatch->getEvent('foo');
    }

    public function testStopWithoutStart()
    {
        self::expectException(\LogicException::class);
        $stopwatch = new Stopwatch();
        $stopwatch->stop('foo');
    }

    public function testMorePrecision()
    {
        $stopwatch = new Stopwatch(true);

        $stopwatch->start('foo');
        $event = $stopwatch->stop('foo');

        self::assertIsFloat($event->getStartTime());
        self::assertIsFloat($event->getEndTime());
        self::assertIsFloat($event->getDuration());
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
        self::assertCount(3, $stopwatch->getSectionEvents('1'));
        self::assertCount(2, $stopwatch->getSectionEvents('2'));
        self::assertCount(2, $stopwatch->getSectionEvents('0'));
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

        self::assertCount(3, $events);
        self::assertCount(2, $events['__section__']->getPeriods());
    }

    public function testReopenANewSectionShouldThrowAnException()
    {
        self::expectException(\LogicException::class);
        $stopwatch = new Stopwatch();
        $stopwatch->openSection('section');
    }

    public function testReset()
    {
        $stopwatch = new Stopwatch();

        $stopwatch->openSection();
        $stopwatch->start('foo', 'cat');

        $stopwatch->reset();

        self::assertEquals(new Stopwatch(), $stopwatch);
    }
}
