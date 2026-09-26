<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use Symfony\Component\Stopwatch\Stopwatch;

class EventDataCollectorTest extends TestCase
{
    public function testGettersOfTheDefaultDispatcherWorkAfterUnserialize()
    {
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $dispatcher->addListener('called', static function () {});
        $dispatcher->addListener('not_called', static function () {});
        $dispatcher->dispatch(new \stdClass(), 'called');
        $dispatcher->dispatch(new \stdClass(), 'orphaned');

        $collector = new EventDataCollector($dispatcher);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        $collector = unserialize(serialize($collector));

        $this->assertCount(1, $collector->getCalledListeners());
        $this->assertCount(1, $collector->getNotCalledListeners());
        $this->assertCount(1, $collector->getOrphanedEvents());
    }
}
