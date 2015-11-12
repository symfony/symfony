<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests\Profiler;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Profiler\EventData;
use Symfony\Component\EventDispatcher\Profiler\EventDataCollector;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;

class EventDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());

        $dispatcher->addListener('test', function () { });

        $c = new EventDataCollector($dispatcher);

        /** @var EventData $data */
        $data = $c->getCollectedData();
        $this->assertInstanceof('Symfony\Component\EventDispatcher\Profiler\EventData', $data);
        $this->assertCount(0, $data->getCalledListeners());
        $this->assertCount(1, $data->getNotCalledListeners());

        $dispatcher->dispatch('test');

        $data = $c->getCollectedData();
        $this->assertCount(1, $data->getCalledListeners());
        $this->assertCount(0, $data->getNotCalledListeners());
    }

    public function testCollectWithoutEventDispatcher()
    {
        $c = new EventDataCollector(null);

        $data = $c->getCollectedData();
        $this->assertNull($data);
    }
}
