<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\SchemeRequestMatcher;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\AccessMap;
use Symfony\Component\Security\Http\Firewall\ChannelListener;

class ChannelListenerTest extends TestCase
{
    public function testHandleWithNotSecuredRequestAndHttpChannel()
    {
        $request = Request::create('http://symfony.com');

        $accessMap = new AccessMap();
        $accessMap->add(new SchemeRequestMatcher('https'), [], 'http');

        $listener = new ChannelListener($accessMap);
        $this->assertFalse($listener->supports($request));
    }

    public function testHandleWithSecuredRequestAndHttpsChannel()
    {
        $request = Request::create('https://symfony.com');

        $accessMap = new AccessMap();
        $accessMap->add(new SchemeRequestMatcher('http'), [], 'https');

        $listener = new ChannelListener($accessMap);
        $this->assertFalse($listener->supports($request));
    }

    public function testHandleWithNotSecuredRequestAndHttpsChannel()
    {
        $request = Request::create('http://symfony.com');

        $accessMap = new AccessMap();
        $accessMap->add(new SchemeRequestMatcher('http'), [], 'https');

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelListener($accessMap);
        $this->assertTrue($listener->supports($request));

        $listener->authenticate($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('https://symfony.com/', $response->getTargetUrl());
    }

    public function testHandleWithSecuredRequestAndHttpChannel()
    {
        $request = Request::create('https://symfony.com');

        $accessMap = new AccessMap();
        $accessMap->add(new SchemeRequestMatcher('https'), [], 'http');

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelListener($accessMap);
        $this->assertTrue($listener->supports($request));

        $listener->authenticate($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('http://symfony.com/', $response->getTargetUrl());
    }

    public function testSupportsWithoutHeaders()
    {
        $request = Request::create('http://symfony.com');
        $request->headers = new HeaderBag();

        $accessMap = new AccessMap();
        $accessMap->add(new SchemeRequestMatcher('http'), [], 'https');

        $listener = new ChannelListener($accessMap);

        $this->assertTrue($listener->supports($request));
    }
}
