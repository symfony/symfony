<?php

namespace Symfony\Bundle\SecurityBundle\Tests\Security\RememberMe;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberMeServicesTest extends \PHPUnit_Framework_TestCase
{
    public function testAutoLoginReturnsNullWhenNoCookie()
    {
        $service = $this->getService(null, array('name' => 'foo'));

        $this->assertNull($service->autoLogin(new Request()));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedMessage processAutoLoginCookie() must return a TokenInterface implementation.
     */
    public function testAutoLoginThrowsExceptionWhenImplementationDoesNotReturnTokenInterface()
    {
        $service = $this->getService(null, array('name' => 'foo'));
        $request = new Request;
        $request->cookies->set('foo', 'foo');

        $service
            ->expects($this->once())
            ->method('processAutoLoginCookie')
            ->will($this->returnValue(null))
        ;

        $service->autoLogin($request);
    }

    public function testAutoLogin()
    {
        $service = $this->getService(null, array('name' => 'foo'));
        $request = new Request();
        $request->cookies->set('foo', 'foo');

        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');

        $service
            ->expects($this->once())
            ->method('processAutoLoginCookie')
            ->will($this->returnValue($token))
        ;

        $returnedToken = $service->autoLogin($request);

        $this->assertSame($token, $returnedToken);
    }

    public function testLogout()
    {
        $service = $this->getService(null, array('name' => 'foo', 'path' => null, 'domain' => null));
        $request = new Request();
        $response = new Response();
        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');

        $this->assertFalse($response->headers->hasCookie('foo'));

        $service->logout($request, $response, $token);

        $this->assertTrue($response->headers->getCookie('foo')->isCleared());
    }

    public function testLoginFail()
    {
        $service = $this->getService(null, array('name' => 'foo', 'path' => null, 'domain' => null));
        $request = new Request();
        $response = new Response();

        $this->assertFalse($response->headers->hasCookie('foo'));

        $service->loginFail($request, $response);

        $this->assertTrue($response->headers->getCookie('foo')->isCleared());
    }

    public function testLoginSuccessIsNotProcessedWhenTokenDoesNotContainAccountInterfaceImplementation()
    {
        $service = $this->getService(null, array('name' => 'foo', 'always_remember_me' => true));
        $request = new Request;
        $response = new Response;
        $account = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $token
            ->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue('foo'))
        ;

        $service
            ->expects($this->never())
            ->method('onLoginSuccess')
        ;

        $this->assertFalse($request->request->has('foo'));

        $service->loginSuccess($request, $response, $token);
    }

    public function testLoginSuccessIsNotProcessedWhenRememberMeIsNotRequested()
    {
        $service = $this->getService(null, array('name' => 'foo', 'always_remember_me' => false, 'remember_me_parameter' => 'foo'));
        $request = new Request;
        $response = new Response;
        $account = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $token
            ->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue($account))
        ;

        $service
            ->expects($this->never())
            ->method('onLoginSuccess')
            ->will($this->returnValue(null))
        ;

        $this->assertFalse($request->request->has('foo'));

        $service->loginSuccess($request, $response, $token);
    }

    public function testLoginSuccessWhenRememberMeAlwaysIsTrue()
    {
        $service = $this->getService(null, array('name' => 'foo', 'always_remember_me' => true));
        $request = new Request;
        $response = new Response;
        $account = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $token
            ->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue($account))
        ;

        $service
            ->expects($this->once())
            ->method('onLoginSuccess')
            ->will($this->returnValue(null))
        ;

        $service->loginSuccess($request, $response, $token);
    }

    /**
     * @dataProvider getPositiveRememberMeParameterValues
     */
    public function testLoginSuccessWhenRememberMeParameterIsPositive($value)
    {
        $service = $this->getService(null, array('name' => 'foo', 'always_remember_me' => false, 'remember_me_parameter' => 'foo'));

        $request = new Request;
        $request->request->set('foo', $value);
        $response = new Response;
        $account = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $token
            ->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue($account))
        ;

        $service
            ->expects($this->once())
            ->method('onLoginSuccess')
            ->will($this->returnValue(true))
        ;

        $service->loginSuccess($request, $response, $token);
    }

    public function getPositiveRememberMeParameterValues()
    {
        return array(
            array('true'),
            array('1'),
            array('on'),
            array('yes'),
        );
    }

    public function testLoginSuccessRenewsRememberMeCookie()
    {
        $service = $this->getService();

        $token = $this->getMock(
            'Symfony\Component\Security\Authentication\Token\RememberMeToken',
            array(),
            array(),
            'NonFunctionalRememberMeTokenMockClass',
            false
        );

        $service
            ->expects($this->once())
            ->method('onLoginSuccess')
            ->will($this->returnValue(null))
        ;

        $service->loginSuccess(new Request(), new Response(), $token);
    }

    protected function getService($userProvider = null, $options = array(), $logger = null)
    {
        if (null === $userProvider) {
            $userProvider = $this->getProvider();
        }

        return $this->getMockForAbstractClass('Symfony\Bundle\SecurityBundle\Security\RememberMe\RememberMeServices', array(
            array($userProvider), 'fookey', 'fookey', $options, $logger
        ));
    }

    protected function getProvider()
    {
        $provider = $this->getMock('Symfony\Component\Security\User\UserProviderInterface');
        $provider
            ->expects($this->any())
            ->method('supportsClass')
            ->will($this->returnValue(true))
        ;

        return $provider;
    }
}
