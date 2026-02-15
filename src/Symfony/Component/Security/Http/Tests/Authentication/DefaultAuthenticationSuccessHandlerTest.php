<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authentication;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;

class DefaultAuthenticationSuccessHandlerTest extends TestCase
{
    #[DataProvider('getRequestRedirections')]
    public function testRequestRedirections(Request $request, $options, $redirectedUrl)
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/login');
        $httpUtils = new HttpUtils($urlGenerator);
        $handler = new DefaultAuthenticationSuccessHandler($httpUtils, $options);
        if ($request->hasSession()) {
            $handler->setFirewallName('admin');
        }
        $this->assertSame('http://localhost'.$redirectedUrl, $handler->onAuthenticationSuccess($request, new NullToken())->getTargetUrl());
    }

    public function testRequestRedirectionsWithTargetPathInSessions()
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.admin.target_path', '/admin/dashboard');
        $requestWithSession = Request::create('/');
        $requestWithSession->setSession($session);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/login');
        $httpUtils = new HttpUtils($urlGenerator);
        $handler = new DefaultAuthenticationSuccessHandler($httpUtils);
        $handler->setFirewallName('admin');

        $this->assertSame('http://localhost/admin/dashboard', $handler->onAuthenticationSuccess($requestWithSession, new NullToken())->getTargetUrl());
        $this->assertFalse($session->has('_security.admin.target_path'));
    }

    public function testStatelessRequestRedirections()
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->never())->method('get')->with('_security.admin.target_path');
        $session->expects($this->never())->method('remove')->with('_security.admin.target_path');
        $statelessRequest = Request::create('/');
        $statelessRequest->setSession($session);
        $statelessRequest->attributes->set('_stateless', true);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/login');
        $httpUtils = new HttpUtils($urlGenerator);
        $handler = new DefaultAuthenticationSuccessHandler($httpUtils);
        $handler->setFirewallName('admin');

        $this->assertSame('http://localhost/', $handler->onAuthenticationSuccess($statelessRequest, new NullToken())->getTargetUrl());
    }

    public static function getRequestRedirections()
    {
        return [
            'default' => [
                Request::create('/'),
                [],
                '/',
            ],
            'forced target path' => [
                Request::create('/'),
                ['always_use_default_target_path' => true, 'default_target_path' => '/dashboard'],
                '/dashboard',
            ],
            'target path as query string' => [
                Request::create('/?_target_path=/dashboard'),
                [],
                '/dashboard',
            ],
            'target path name as query string is customized' => [
                Request::create('/?_my_target_path=/dashboard'),
                ['target_path_parameter' => '_my_target_path'],
                '/dashboard',
            ],
            'target path name as query string is customized and nested' => [
                Request::create('/?_target_path[value]=/dashboard'),
                ['target_path_parameter' => '_target_path[value]'],
                '/dashboard',
            ],
            'target path as referer' => [
                Request::create('/', 'GET', [], [], [], ['HTTP_REFERER' => 'http://localhost/dashboard']),
                ['use_referer' => true],
                '/dashboard',
            ],
            'target path as referer is ignored if not configured' => [
                Request::create('/', 'GET', [], [], [], ['HTTP_REFERER' => 'http://localhost/dashboard']),
                [],
                '/',
            ],
            'target path as referer when referer not set' => [
                Request::create('/'),
                ['use_referer' => true],
                '/',
            ],
            'target path as referer when referer is ?' => [
                Request::create('/', 'GET', [], [], [], ['HTTP_REFERER' => '?']),
                ['use_referer' => true],
                '/',
            ],
            'target path should be different than login URL' => [
                Request::create('/', 'GET', [], [], [], ['HTTP_REFERER' => 'http://localhost/login']),
                ['use_referer' => true, 'login_path' => '/login'],
                '/',
            ],
            'target path should be different than login URL (query string does not matter)' => [
                Request::create('/', 'GET', [], [], [], ['HTTP_REFERER' => 'http://localhost/login?t=1&p=2']),
                ['use_referer' => true, 'login_path' => '/login'],
                '/',
            ],
            'target path should be different than login URL (login_path as a route)' => [
                Request::create('/', 'GET', [], [], [], ['HTTP_REFERER' => 'http://localhost/login?t=1&p=2']),
                ['use_referer' => true, 'login_path' => 'login_route'],
                '/',
            ],
        ];
    }

    public function testTargetPathFromRequestWithInvalidUrl()
    {
        $options = ['target_path_parameter' => '_my_target_path'];

        $request = Request::create('/');
        $request->attributes->set('_my_target_path', 'some_route_name');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with('Ignoring query parameter "_my_target_path": not a valid URL.');

        $handler = new DefaultAuthenticationSuccessHandler(new HttpUtils($this->createStub(UrlGeneratorInterface::class)), $options, $logger);

        $handler->onAuthenticationSuccess($request, new NullToken());
    }

    public function testTargetPathWithAbsoluteUrlFromRequest()
    {
        $options = ['target_path_parameter' => '_my_target_path'];

        $request = Request::create('/');
        $request->attributes->set('_my_target_path', 'https://localhost/some-path');

        $handler = new DefaultAuthenticationSuccessHandler(new HttpUtils($this->createStub(UrlGeneratorInterface::class)), $options);
        $response = $handler->onAuthenticationSuccess($request, new NullToken());

        $this->assertEquals(new RedirectResponse('https://localhost/some-path'), $response);
    }
}
