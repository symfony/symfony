<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\EventListener\RememberMeListener;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

class RememberMeListenerTest extends TestCase
{
    private $rememberMeServices;
    private $listener;
    private $request;
    private $response;
    private $token;

    protected function setUp(): void
    {
        $this->rememberMeServices = $this->createMock(RememberMeServicesInterface::class);
        $this->listener = new RememberMeListener($this->rememberMeServices);
        $this->request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $this->response = $this->createMock(Response::class);
        $this->token = $this->createMock(TokenInterface::class);
    }

    /**
     * @dataProvider provideUnsupportingAuthenticators
     */
    public function testSuccessfulLoginWithoutSupportingAuthenticator($authenticator)
    {
        $this->rememberMeServices->expects($this->never())->method('loginSuccess');

        $event = $this->createLoginSuccessfulEvent('main_firewall', $this->response, $authenticator);
        $this->listener->onSuccessfulLogin($event);
    }

    public function provideUnsupportingAuthenticators()
    {
        yield [$this->createMock(AuthenticatorInterface::class)];

        $authenticator = $this->createMock([AuthenticatorInterface::class, RememberMeAuthenticatorInterface::class]);
        $authenticator->expects($this->any())->method('supportsRememberMe')->willReturn(false);
        yield [$authenticator];
    }

    public function testSuccessfulLoginWithoutSuccessResponse()
    {
        $this->rememberMeServices->expects($this->never())->method('loginSuccess');

        $event = $this->createLoginSuccessfulEvent('main_firewall', null);
        $this->listener->onSuccessfulLogin($event);
    }

    public function testSuccessfulLogin()
    {
        $this->rememberMeServices->expects($this->once())->method('loginSuccess')->with($this->request, $this->response, $this->token);

        $event = $this->createLoginSuccessfulEvent('main_firewall', $this->response);
        $this->listener->onSuccessfulLogin($event);
    }

    public function testCredentialsInvalid()
    {
        $this->rememberMeServices->expects($this->once())->method('loginFail')->with($this->request, $this->isInstanceOf(AuthenticationException::class));

        $event = $this->createLoginFailureEvent('main_firewall');
        $this->listener->onFailedLogin($event);
    }

    private function createLoginSuccessfulEvent($providerKey, $response, $authenticator = null)
    {
        if (null === $authenticator) {
            $authenticator = $this->createMock([AuthenticatorInterface::class, RememberMeAuthenticatorInterface::class]);
            $authenticator->expects($this->any())->method('supportsRememberMe')->willReturn(true);
        }

        return new LoginSuccessEvent($authenticator, $this->token, $this->request, $response, $providerKey);
    }

    private function createLoginFailureEvent($providerKey)
    {
        return new LoginFailureEvent(new AuthenticationException(), $this->createMock(AuthenticatorInterface::class), $this->request, null, $providerKey);
    }
}
