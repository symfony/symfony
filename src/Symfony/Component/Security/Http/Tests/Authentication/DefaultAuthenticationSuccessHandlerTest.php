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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;

class DefaultAuthenticationSuccessHandlerTest extends TestCase
{
    private $httpUtils = null;
    private $token = null;

    protected function setUp()
    {
        $this->httpUtils = $this->getMockBuilder('Symfony\Component\Security\Http\HttpUtils')->getMock();
        $this->token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
    }

    public function testRequestIsRedirected()
    {
        $request = Request::create('/');
        $response = $this->expectRedirectResponse($request, '/');

        $handler = new DefaultAuthenticationSuccessHandler($this->httpUtils, array());
        $result = $handler->onAuthenticationSuccess($request, $this->token);

        $this->assertSame($response, $result);
    }

    public function testDefaultTargetPathCanBeForced()
    {
        $options = array(
            'always_use_default_target_path' => true,
            'default_target_path' => '/dashboard',
        );

        $request = Request::create('/');
        $response = $this->expectRedirectResponse($request, '/dashboard');

        $handler = new DefaultAuthenticationSuccessHandler($this->httpUtils, $options);
        $result = $handler->onAuthenticationSuccess($request, $this->token);

        $this->assertSame($response, $result);
    }

    public function testTargetPathIsPassedWithRequest()
    {
        $request = Request::create('/?_target_path=/dashboard');
        $response = $this->expectRedirectResponse($request, '/dashboard');

        $handler = new DefaultAuthenticationSuccessHandler($this->httpUtils, array());
        $result = $handler->onAuthenticationSuccess($request, $this->token);

        $this->assertSame($response, $result);
    }

    public function testTargetPathParameterIsCustomised()
    {
        $options = array('target_path_parameter' => '_my_target_path');
        $request = Request::create('/?_my_target_path=/dashboard');
        $response = $this->expectRedirectResponse($request, '/dashboard');

        $handler = new DefaultAuthenticationSuccessHandler($this->httpUtils, $options);
        $result = $handler->onAuthenticationSuccess($request, $this->token);

        $this->assertSame($response, $result);
    }

    public function testTargetPathIsTakenFromTheSession()
    {
        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock();
        $session->expects($this->once())
            ->method('get')->with('_security.admin.target_path')
            ->will($this->returnValue('/admin/dashboard'));
        $session->expects($this->once())
            ->method('remove')->with('_security.admin.target_path');

        $request = Request::create('/?_my_target_path=/dashboard');
        $request->setSession($session);
        $response = $this->expectRedirectResponse($request, '/admin/dashboard');

        $handler = new DefaultAuthenticationSuccessHandler($this->httpUtils, array());
        $handler->setProviderKey('admin');

        $result = $handler->onAuthenticationSuccess($request, $this->token);

        $this->assertSame($response, $result);
    }

    public function testTargetPathIsPassedAsReferer()
    {
        $options = array('use_referer' => true);
        $request = Request::create('/');
        $request->headers->set('Referer', '/dashboard');
        $response = $this->expectRedirectResponse($request, '/dashboard');

        $handler = new DefaultAuthenticationSuccessHandler($this->httpUtils, $options);
        $result = $handler->onAuthenticationSuccess($request, $this->token);

        $this->assertSame($response, $result);
    }

    public function testRefererHasToBeDifferentThatLoginUrl()
    {
        $options = array('use_referer' => true);
        $request = Request::create('/');
        $request->headers->set('Referer', '/login');
        $this->httpUtils->expects($this->once())
            ->method('generateUri')->with($request, '/login')
            ->will($this->returnValue('/login'));

        $response = $this->expectRedirectResponse($request, '/');

        $handler = new DefaultAuthenticationSuccessHandler($this->httpUtils, $options);
        $result = $handler->onAuthenticationSuccess($request, $this->token);

        $this->assertSame($response, $result);
    }

    public function testRefererTargetPathIsIgnoredByDefault()
    {
        $request = Request::create('/');
        $response = $this->expectRedirectResponse($request, '/');

        $handler = new DefaultAuthenticationSuccessHandler($this->httpUtils, array());
        $result = $handler->onAuthenticationSuccess($request, $this->token);

        $this->assertSame($response, $result);
    }

    private function expectRedirectResponse(Request $request, $path)
    {
        $response = new Response();
        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')
            ->with($request, $path)
            ->will($this->returnValue($response));

        return $response;
    }
}
