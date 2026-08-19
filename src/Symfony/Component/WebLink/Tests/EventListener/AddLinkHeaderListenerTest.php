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

        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertInstanceOf(EventSubscriberInterface::class, $subscriber);

        $expected = [
            '<https://demo.api-platform.com/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"',
            '</foo>; rel="preload"',
        ];

        $this->assertEquals($expected, $response->headers->all()['link']);
        $this->assertFalse($response->headers->has('Link-Template'));
    }

    public function testOnKernelResponseSendsTemplatedLinksInTheLinkTemplateHeader()
    {
        $links = new GenericLinkProvider([
            new Link('preload', '/foo'),
            new Link('item', '/users/{id}'),
        ]);
        $response = $this->dispatch($links);

        $this->assertSame('</foo>; rel="preload"', $response->headers->get('Link'));
        $this->assertSame('"/users/{id}"; rel="item"', $response->headers->get('Link-Template'));
    }

    public function testOnKernelResponseDoesNotSendAnEmptyLinkHeader()
    {
        $response = $this->dispatch(new GenericLinkProvider([new Link('item', '/users/{id}')]));

        $this->assertFalse($response->headers->has('Link'));
        $this->assertSame('"/users/{id}"; rel="item"', $response->headers->get('Link-Template'));
    }

    public function testOnKernelResponseWithoutLinks()
    {
        $response = $this->dispatch(new GenericLinkProvider());

        $this->assertFalse($response->headers->has('Link'));
        $this->assertFalse($response->headers->has('Link-Template'));
    }

    public function testOnKernelResponseIgnoresSubRequests()
    {
        $request = new Request([], [], ['_links' => new GenericLinkProvider([new Link('preload', '/foo')])]);
        $response = new Response();

        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST, $response);
        (new AddLinkHeaderListener())->onKernelResponse($event);

        $this->assertFalse($response->headers->has('Link'));
    }

    public function testSubscribedEvents()
    {
        $this->assertEquals([KernelEvents::RESPONSE => 'onKernelResponse'], AddLinkHeaderListener::getSubscribedEvents());
    }

    private function dispatch(GenericLinkProvider $links): Response
    {
        $request = new Request([], [], ['_links' => $links]);
        $response = new Response();

        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
        (new AddLinkHeaderListener())->onKernelResponse($event);

        return $response;
    }
}
