<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseListenerTest extends TestCase
{
    private EventDispatcher $dispatcher;
    private MockObject&HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $listener = new ResponseListener('UTF-8');
        $this->dispatcher->addListener(KernelEvents::RESPONSE, $listener->onKernelResponse(...));

        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testFilterDoesNothingForSubRequests()
    {
        $response = new Response('foo');

        $event = new ResponseEvent($this->kernel, new Request(), HttpKernelInterface::SUB_REQUEST, $response);
        $this->dispatcher->dispatch($event, KernelEvents::RESPONSE);

        $this->assertEquals('', $event->getResponse()->headers->get('content-type'));
    }

    public function testFilterSetsNonDefaultCharsetIfNotOverridden()
    {
        $listener = new ResponseListener('ISO-8859-15');
        $this->dispatcher->addListener(KernelEvents::RESPONSE, $listener->onKernelResponse(...), 1);

        $response = new Response('foo');

        $event = new ResponseEvent($this->kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $response);
        $this->dispatcher->dispatch($event, KernelEvents::RESPONSE);

        $this->assertEquals('ISO-8859-15', $response->getCharset());
    }

    public function testFilterDoesNothingIfCharsetIsOverridden()
    {
        $listener = new ResponseListener('ISO-8859-15');
        $this->dispatcher->addListener(KernelEvents::RESPONSE, $listener->onKernelResponse(...), 1);

        $response = new Response('foo');
        $response->setCharset('ISO-8859-1');

        $event = new ResponseEvent($this->kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $response);
        $this->dispatcher->dispatch($event, KernelEvents::RESPONSE);

        $this->assertEquals('ISO-8859-1', $response->getCharset());
    }

    public function testFiltersSetsNonDefaultCharsetIfNotOverriddenOnNonTextContentType()
    {
        $listener = new ResponseListener('ISO-8859-15');
        $this->dispatcher->addListener(KernelEvents::RESPONSE, $listener->onKernelResponse(...), 1);

        $response = new Response('foo');
        $request = Request::create('/');
        $request->setRequestFormat('application/json');

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $this->dispatcher->dispatch($event, KernelEvents::RESPONSE);

        $this->assertEquals('ISO-8859-15', $response->getCharset());
    }

    public function testSetContentLanguageHeaderWhenEmptyAndAtLeast2EnabledLocalesAreConfigured()
    {
        $listener = new ResponseListener('ISO-8859-15', true, ['fr', 'en']);
        $this->dispatcher->addListener(KernelEvents::RESPONSE, $listener->onKernelResponse(...), 1);

        $response = new Response('content');
        $request = Request::create('/');
        $request->setLocale('fr');

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $this->dispatcher->dispatch($event, KernelEvents::RESPONSE);

        $this->assertEquals('fr', $response->headers->get('Content-Language'));
    }

    public function testNotOverrideContentLanguageHeaderWhenNotEmpty()
    {
        $listener = new ResponseListener('ISO-8859-15', true, ['de']);
        $this->dispatcher->addListener(KernelEvents::RESPONSE, $listener->onKernelResponse(...), 1);

        $response = new Response('content');
        $response->headers->set('Content-Language', 'mi, en');
        $request = Request::create('/');
        $request->setLocale('de');

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $this->dispatcher->dispatch($event, KernelEvents::RESPONSE);

        $this->assertEquals('mi, en', $response->headers->get('Content-Language'));
    }

    public function testNotSetContentLanguageHeaderWhenDisabled()
    {
        $listener = new ResponseListener('ISO-8859-15', false);
        $this->dispatcher->addListener(KernelEvents::RESPONSE, $listener->onKernelResponse(...), 1);

        $response = new Response('content');
        $request = Request::create('/');
        $request->setLocale('fr');

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $this->dispatcher->dispatch($event, KernelEvents::RESPONSE);

        $this->assertNull($response->headers->get('Content-Language'));
    }
}
