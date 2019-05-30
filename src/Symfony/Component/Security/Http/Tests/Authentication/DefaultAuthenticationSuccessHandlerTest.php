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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;

class DefaultAuthenticationSuccessHandlerTest extends TestCase
{
    /**
     * @dataProvider getRequestRedirections
     */
    public function testRequestRedirections(Request $request, $options, $redirectedUrl)
    {
        $urlGenerator = $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock();
        $urlGenerator->expects($this->any())->method('generate')->willReturn('http://localhost/login');
        $httpUtils = new HttpUtils($urlGenerator);
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $handler = new DefaultAuthenticationSuccessHandler($httpUtils, $options);
        if ($request->hasSession()) {
            $handler->setProviderKey('admin');
        }
        $this->assertSame('http://localhost'.$redirectedUrl, $handler->onAuthenticationSuccess($request, $token)->getTargetUrl());
    }

    public function getRequestRedirections()
    {
        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock();
        $session->expects($this->once())->method('get')->with('_security.admin.target_path')->willReturn('/admin/dashboard');
        $session->expects($this->once())->method('remove')->with('_security.admin.target_path');
        $requestWithSession = Request::create('/');
        $requestWithSession->setSession($session);

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
            'target path in session' => [
                $requestWithSession,
                [],
                '/admin/dashboard',
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
}
