<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\Firewall\GuardAuthenticationListener;
use Symfony\Component\Security\Guard\Token\PreAuthenticationGuardToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Amaury Leroux de Lens <amaury@lerouxdelens.com>
 */
class GuardAuthenticationListenerTest extends TestCase
{
    private $authenticationManager;
    private $guardAuthenticatorHandler;
    private $event;
    private $logger;
    private $request;
    private $rememberMeServices;

    public function testHandleSuccess()
    {
        $authenticator = $this->getMockBuilder(AuthenticatorInterface::class)->getMock();
        $authenticateToken = $this->getMockBuilder(TokenInterface::class)->getMock();
        $providerKey = 'my_firewall';

        $credentials = array('username' => 'weaverryan', 'password' => 'all_your_base');

        $authenticator
            ->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $authenticator
            ->expects($this->once())
            ->method('getCredentials')
            ->with($this->equalTo($this->request))
            ->will($this->returnValue($credentials));

        // a clone of the token that should be created internally
        $uniqueGuardKey = 'my_firewall_0';
        $nonAuthedToken = new PreAuthenticationGuardToken($credentials, $uniqueGuardKey);

        $this->authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->equalTo($nonAuthedToken))
            ->will($this->returnValue($authenticateToken));

        $this->guardAuthenticatorHandler
            ->expects($this->once())
            ->method('authenticateWithToken')
            ->with($authenticateToken, $this->request);

        $this->guardAuthenticatorHandler
            ->expects($this->once())
            ->method('handleAuthenticationSuccess')
            ->with($authenticateToken, $this->request, $authenticator, $providerKey);

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            $providerKey,
            array($authenticator),
            $this->logger
        );

        $listener->setRememberMeServices($this->rememberMeServices);
        // should never be called - our handleAuthenticationSuccess() does not return a Response
        $this->rememberMeServices
            ->expects($this->never())
            ->method('loginSuccess');

        $listener->handle($this->event);
    }

    public function testHandleSuccessStopsAfterResponseIsSet()
    {
        $authenticator1 = $this->getMockBuilder(AuthenticatorInterface::class)->getMock();
        $authenticator2 = $this->getMockBuilder(AuthenticatorInterface::class)->getMock();

        // mock the first authenticator to fail, and set a Response
        $authenticator1
            ->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $authenticator1
            ->expects($this->once())
            ->method('getCredentials')
            ->willThrowException(new AuthenticationException());
        $this->guardAuthenticatorHandler
            ->expects($this->once())
            ->method('handleAuthenticationFailure')
            ->willReturn(new Response());
        // the second authenticator should *never* be called
        $authenticator2
            ->expects($this->never())
            ->method('getCredentials');

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            'my_firewall',
            array($authenticator1, $authenticator2),
            $this->logger
        );

        $listener->handle($this->event);
    }

    public function testHandleSuccessWithRememberMe()
    {
        $authenticator = $this->getMockBuilder(AuthenticatorInterface::class)->getMock();
        $authenticateToken = $this->getMockBuilder(TokenInterface::class)->getMock();
        $providerKey = 'my_firewall_with_rememberme';

        $authenticator
            ->expects($this->once())
            ->method('supports')
            ->with($this->equalTo($this->request))
            ->willReturn(true);
        $authenticator
            ->expects($this->once())
            ->method('getCredentials')
            ->with($this->equalTo($this->request))
            ->will($this->returnValue(array('username' => 'anything_not_empty')));

        $this->authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->will($this->returnValue($authenticateToken));

        $successResponse = new Response('Success!');
        $this->guardAuthenticatorHandler
            ->expects($this->once())
            ->method('handleAuthenticationSuccess')
            ->will($this->returnValue($successResponse));

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            $providerKey,
            array($authenticator),
            $this->logger
        );

        $listener->setRememberMeServices($this->rememberMeServices);
        $authenticator->expects($this->once())
            ->method('supportsRememberMe')
            ->will($this->returnValue(true));
        // should be called - we do have a success Response
        $this->rememberMeServices
            ->expects($this->once())
            ->method('loginSuccess');

        $listener->handle($this->event);
    }

    public function testHandleCatchesAuthenticationException()
    {
        $authenticator = $this->getMockBuilder(AuthenticatorInterface::class)->getMock();
        $providerKey = 'my_firewall2';

        $authException = new AuthenticationException('Get outta here crazy user with a bad password!');
        $authenticator
            ->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $authenticator
            ->expects($this->once())
            ->method('getCredentials')
            ->will($this->throwException($authException));

        // this is not called
        $this->authenticationManager
            ->expects($this->never())
            ->method('authenticate');

        $this->guardAuthenticatorHandler
            ->expects($this->once())
            ->method('handleAuthenticationFailure')
            ->with($authException, $this->request, $authenticator, $providerKey);

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            $providerKey,
            array($authenticator),
            $this->logger
        );

        $listener->handle($this->event);
    }

    public function testSupportsReturnFalseSkipAuth()
    {
        $authenticator = $this->getMockBuilder(AuthenticatorInterface::class)->getMock();
        $providerKey = 'my_firewall4';

        $authenticator
            ->expects($this->once())
            ->method('supports')
            ->will($this->returnValue(false));

        // this is not called
        $authenticator
            ->expects($this->never())
            ->method('getCredentials');

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            $providerKey,
            array($authenticator),
            $this->logger
        );

        $listener->handle($this->event);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testReturnNullFromGetCredentials()
    {
        $authenticator = $this->getMockBuilder(AuthenticatorInterface::class)->getMock();
        $providerKey = 'my_firewall4';

        $authenticator
            ->expects($this->once())
            ->method('supports')
            ->will($this->returnValue(true));

        // this will raise exception
        $authenticator
            ->expects($this->once())
            ->method('getCredentials')
            ->will($this->returnValue(null));

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            $providerKey,
            array($authenticator),
            $this->logger
        );

        $listener->handle($this->event);
    }

    protected function setUp()
    {
        $this->authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->guardAuthenticatorHandler = $this->getMockBuilder('Symfony\Component\Security\Guard\GuardAuthenticatorHandler')
            ->disableOriginalConstructor()
            ->getMock();

        $this->request = new Request(array(), array(), array(), array(), array(), array());

        $this->event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->setMethods(array('getRequest'))
            ->getMock();
        $this->event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($this->request));

        $this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $this->rememberMeServices = $this->getMockBuilder('Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface')->getMock();
    }

    protected function tearDown()
    {
        $this->authenticationManager = null;
        $this->guardAuthenticatorHandler = null;
        $this->event = null;
        $this->logger = null;
        $this->request = null;
        $this->rememberMeServices = null;
    }
}
