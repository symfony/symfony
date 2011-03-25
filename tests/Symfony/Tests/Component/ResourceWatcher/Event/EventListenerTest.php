<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\ResourceWatcher\Event;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\ResourceWatcher\Event\Event;
use Symfony\Component\ResourceWatcher\Event\EventListener;

class EventListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructAndGetters()
    {
        $listener = new EventListener($res = new FileResource(__FILE__), $cb = function(){}, Event::CREATED);

        $this->assertSame($res, $listener->getResource());
        $this->assertSame($cb, $listener->getCallback());
    }

    public function testHandles()
    {
        $res    = new FileResource(__FILE__);
        $cb     = function(){};

        $listener = new EventListener($res, $cb, Event::CREATED);

        $this->assertTrue($listener->handles(new Event(1, $res, $type = Event::CREATED)));
        $this->assertFalse($listener->handles(new Event(1, $res, $type = Event::MODIFIED)));
        $this->assertFalse($listener->handles(new Event(1, $res, $type = Event::DELETED)));

        $listener = new EventListener($res, $cb, Event::CREATED | Event::DELETED);

        $this->assertTrue($listener->handles(new Event(1, $res, $type = Event::CREATED)));
        $this->assertFalse($listener->handles(new Event(1, $res, $type = Event::MODIFIED)));
        $this->assertTrue($listener->handles(new Event(1, $res, $type = Event::DELETED)));

        $listener = new EventListener($res, $cb, Event::ALL);

        $this->assertTrue($listener->handles(new Event(1, $res, $type = Event::CREATED)));
        $this->assertTrue($listener->handles(new Event(1, $res, $type = Event::MODIFIED)));
        $this->assertTrue($listener->handles(new Event(1, $res, $type = Event::DELETED)));

        $listener = new EventListener($res, $cb, Event::DELETED);

        $this->assertFalse($listener->handles(new Event(1, $res, $type = Event::CREATED)));
        $this->assertFalse($listener->handles(new Event(1, $res, $type = Event::MODIFIED)));
        $this->assertTrue($listener->handles(new Event(1, $res, $type = Event::DELETED)));
    }
}
