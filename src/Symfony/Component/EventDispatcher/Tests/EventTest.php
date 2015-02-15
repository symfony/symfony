<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Test class for Event.
 */
class EventTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\EventDispatcher\Event
     */
    protected $event;

    /**
<<<<<<< HEAD
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     */
    protected $dispatcher;

    /**
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->event = new Event();
<<<<<<< HEAD
        $this->dispatcher = new EventDispatcher();
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        $this->event = null;
<<<<<<< HEAD
        $this->dispatcher = null;
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
    }

    public function testIsPropagationStopped()
    {
        $this->assertFalse($this->event->isPropagationStopped());
    }

    public function testStopPropagationAndIsPropagationStopped()
    {
        $this->event->stopPropagation();
        $this->assertTrue($this->event->isPropagationStopped());
    }
<<<<<<< HEAD

    public function testLegacySetDispatcher()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);
        $this->event->setDispatcher($this->dispatcher);
        $this->assertSame($this->dispatcher, $this->event->getDispatcher());
    }

    public function testLegacyGetDispatcher()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);
        $this->assertNull($this->event->getDispatcher());
    }

    public function testLegacyGetName()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);
        $this->assertNull($this->event->getName());
    }

    public function testLegacySetName()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);
        $this->event->setName('foo');
        $this->assertEquals('foo', $this->event->getName());
    }
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
}
