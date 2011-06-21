<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class EventDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $c = new EventDataCollector();
        $c->setEventDispatcher(new TestEventDispatcher());

        $c->collect(new Request(), new Response());

        $this->assertSame('events',$c->getName());
        $this->assertSame(array('foo'),$c->getCalledListeners());
        $this->assertSame(array('bar'),$c->getNotCalledListeners());
    }

}

class TestEventDispatcher extends EventDispatcher implements TraceableEventDispatcherInterface
{
    function getCalledListeners()
    {
        return array('foo');
    }

    function getNotCalledListeners()
    {
        return array('bar');
    }
}
