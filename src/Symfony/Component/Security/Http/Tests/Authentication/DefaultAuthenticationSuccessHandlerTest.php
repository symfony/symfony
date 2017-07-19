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
        $urlGenerator->expects($this->any())->method('generate')->will($this->returnValue('http://localhost/login'));
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
        $session->expects($this->once())->method('get')->with('_security.admin.target_path')->will($this->returnValue('/admin/dashboard'));
        $session->expects($this->once())->method('remove')->with('_security.admin.target_path');
        $requestWithSession = Request::create('/');
        $requestWithSession->setSession($session);

        return array(
            'default' => array(
                Request::create('/'),
                array(),
                '/',
            ),
            'forced target path' => array(
                Request::create('/'),
                array('always_use_default_target_path' => true, 'default_target_path' => '/dashboard'),
                '/dashboard',
            ),
            'target path as query string' => array(
                Request::create('/?_target_path=/dashboard'),
                array(),
                '/dashboard',
            ),
            'target path name as query string is customized' => array(
                Request::create('/?_my_target_path=/dashboard'),
                array('target_path_parameter' => '_my_target_path'),
                '/dashboard',
            ),
            'target path name as query string is customized and nested' => array(
                Request::create('/?_target_path[value]=/dashboard'),
                array('target_path_parameter' => '_target_path[value]'),
                '/dashboard',
            ),
            'target path in session' => array(
                $requestWithSession,
                array(),
                '/admin/dashboard',
            ),
            'target path as referer' => array(
                Request::create('/', 'GET', array(), array(), array(), array('HTTP_REFERER' => 'http://localhost/dashboard')),
                array('use_referer' => true),
                '/dashboard',
            ),
            'target path as referer is ignored if not configured' => array(
                Request::create('/', 'GET', array(), array(), array(), array('HTTP_REFERER' => 'http://localhost/dashboard')),
                array(),
                '/',
            ),
            'target path should be different than login URL' => array(
                Request::create('/', 'GET', array(), array(), array(), array('HTTP_REFERER' => 'http://localhost/login')),
                array('use_referer' => true, 'login_path' => '/login'),
                '/',
            ),
            'target path should be different than login URL (query string does not matter)' => array(
                Request::create('/', 'GET', array(), array(), array(), array('HTTP_REFERER' => 'http://localhost/login?t=1&p=2')),
                array('use_referer' => true, 'login_path' => '/login'),
                '/',
            ),
            'target path should be different than login URL (login_path as a route)' => array(
                Request::create('/', 'GET', array(), array(), array(), array('HTTP_REFERER' => 'http://localhost/login?t=1&p=2')),
                array('use_referer' => true, 'login_path' => 'login_route'),
                '/',
            ),
        );
    }
}
