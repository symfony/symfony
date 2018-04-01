<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\WebLink\Tests\EventListener;

use Fig\Link\GenericLinkProvider;
use Fig\Link\Link;
use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\WebLink\EventListener\AddLinkHeaderListener;
use Symphony\Component\EventDispatcher\EventSubscriberInterface;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Event\FilterResponseEvent;
use Symphony\Component\HttpKernel\KernelEvents;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AddLinkHeaderListenerTest extends TestCase
{
    public function testOnKernelResponse()
    {
        $request = new Request(array(), array(), array('_links' => new GenericLinkProvider(array(new Link('preload', '/foo')))));
        $response = new Response('', 200, array('Link' => '<https://demo.api-platform.com/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"'));

        $subscriber = new AddLinkHeaderListener();

        $event = $this->getMockBuilder(FilterResponseEvent::class)->disableOriginalConstructor()->getMock();
        $event->method('isMasterRequest')->willReturn(true);
        $event->method('getRequest')->willReturn($request);
        $event->method('getResponse')->willReturn($response);

        $subscriber->onKernelResponse($event);

        $this->assertInstanceOf(EventSubscriberInterface::class, $subscriber);

        $expected = array(
            '<https://demo.api-platform.com/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"',
            '</foo>; rel="preload"',
        );

        $this->assertEquals($expected, $response->headers->get('Link', null, false));
    }

    public function testSubscribedEvents()
    {
        $this->assertEquals(array(KernelEvents::RESPONSE => 'onKernelResponse'), AddLinkHeaderListener::getSubscribedEvents());
    }
}
