<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Debug;

use Symfony\Component\HttpKernel\Debug\Stopwatch;

/**
 * StopwatchTest
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class StopwatchTest extends \PHPUnit_Framework_TestCase
{
    public function testStart()
    {
        $stopwatch = new Stopwatch();
        $event = $stopwatch->start('foo', 'cat');

        $this->assertInstanceof('Symfony\Component\HttpKernel\Debug\StopwatchEvent', $event);
        $this->assertEquals('cat', $event->getCategory());
    }

    public function testStop()
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('foo', 'cat');
        usleep(20000);
        $event = $stopwatch->stop('foo');

        $this->assertInstanceof('Symfony\Component\HttpKernel\Debug\StopwatchEvent', $event);
        $total = $event->getTotalTime();
        $this->assertTrue($total > 10 && $total <= 29, $total.' should be 20 (between 10 and 29)');
    }

    public function testLap()
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('foo', 'cat');
        usleep(10000);
        $event = $stopwatch->lap('foo');
        usleep(10000);
        $stopwatch->stop('foo');

        $this->assertInstanceof('Symfony\Component\HttpKernel\Debug\StopwatchEvent', $event);
        $total = $event->getTotalTime();
        $this->assertTrue($total > 10 && $total <= 29, $total.' should be 20 (between 10 and 29)');
    }

    /**
     * @expectedException \LogicException
     */
    public function testStopWithoutStart()
    {
        $stopwatch = new Stopwatch();
        $stopwatch->stop('foo');
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

    /**
     * @expectedException \LogicException
     */
    public function testReopenANewSectionShouldThrowAnException()
    {
        $stopwatch = new Stopwatch();
        $stopwatch->openSection('section');
    }
}
