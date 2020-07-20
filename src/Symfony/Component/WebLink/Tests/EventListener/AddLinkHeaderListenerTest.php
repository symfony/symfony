<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\WebLink\EventListener\AddLinkHeaderListener;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AddLinkHeaderListenerTest extends TestCase
{
    public function testOnKernelResponse()
    {
        $request = new Request([], [], ['_links' => new GenericLinkProvider([new Link('preload', '/foo')])]);
        $response = new Response('', 200, ['Link' => '<https://demo.api-platform.com/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"']);

        $subscriber = new AddLinkHeaderListener();

        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertInstanceOf(EventSubscriberInterface::class, $subscriber);

        $expected = [
            '<https://demo.api-platform.com/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"',
            '</foo>; rel="preload"',
        ];

        $this->assertEquals($expected, $response->headers->all()['link']);
    }

    public function testSubscribedEvents()
    {
        $this->assertEquals([KernelEvents::RESPONSE => 'onKernelResponse'], AddLinkHeaderListener::getSubscribedEvents());
    }
}
