<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpKernel\EventListener\AddRequestFormatsListener;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\KernelEvents;

/**
 * Test AddRequestFormatsListener class.
 *
 * @author Gildas Quemener <gildas.quemener@gmail.com>
 */
class AddRequestFormatsListenerTest extends TestCase
{
    /**
     * @var AddRequestFormatsListener
     */
    private $listener;

    protected function setUp()
    {
        $this->listener = new AddRequestFormatsListener(array('csv' => array('text/csv', 'text/plain')));
    }

    protected function tearDown()
    {
        $this->listener = null;
    }

    public function testIsAnEventSubscriber()
    {
        $this->assertInstanceOf('Symphony\Component\EventDispatcher\EventSubscriberInterface', $this->listener);
    }

    public function testRegisteredEvent()
    {
        $this->assertEquals(
            array(KernelEvents::REQUEST => array('onKernelRequest', 1)),
            AddRequestFormatsListener::getSubscribedEvents()
        );
    }

    public function testSetAdditionalFormats()
    {
        $request = $this->getRequestMock();
        $event = $this->getGetResponseEventMock($request);

        $request->expects($this->once())
            ->method('setFormat')
            ->with('csv', array('text/csv', 'text/plain'));

        $this->listener->onKernelRequest($event);
    }

    protected function getRequestMock()
    {
        return $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')->getMock();
    }

    protected function getGetResponseEventMock(Request $request)
    {
        $event = $this
            ->getMockBuilder('Symphony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        return $event;
    }
}
