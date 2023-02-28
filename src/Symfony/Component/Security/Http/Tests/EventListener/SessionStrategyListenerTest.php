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
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\EventListener\SessionStrategyListener;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

class SessionStrategyListenerTest extends TestCase
{
    private $sessionAuthenticationStrategy;
    private $listener;
    private $request;
    private $token;

    protected function setUp(): void
    {
        $this->sessionAuthenticationStrategy = $this->createMock(SessionAuthenticationStrategyInterface::class);
        $this->listener = new SessionStrategyListener($this->sessionAuthenticationStrategy);
        $this->request = new Request();
        $this->token = $this->createMock(NullToken::class);
    }

    public function testRequestWithSession()
    {
        $this->configurePreviousSession();

        $this->sessionAuthenticationStrategy->expects($this->once())->method('onAuthentication')->with($this->request, $this->token);

        $this->listener->onSuccessfulLogin($this->createEvent('main_firewall'));
    }

    public function testRequestWithoutPreviousSession()
    {
        $this->sessionAuthenticationStrategy->expects($this->never())->method('onAuthentication')->with($this->request, $this->token);

        $this->listener->onSuccessfulLogin($this->createEvent('main_firewall'));
    }

    public function testStatelessFirewalls()
    {
        $this->sessionAuthenticationStrategy->expects($this->never())->method('onAuthentication');

        $listener = new SessionStrategyListener($this->sessionAuthenticationStrategy, ['api_firewall']);
        $listener->onSuccessfulLogin($this->createEvent('api_firewall'));
    }

    public function testRequestWithSamePreviousUser()
    {
        $this->configurePreviousSession();
        $this->sessionAuthenticationStrategy->expects($this->never())->method('onAuthentication');

        $token = $this->createMock(NullToken::class);
        $token->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('test');
        $previousToken = $this->createMock(NullToken::class);
        $previousToken->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('test');

        $event = new LoginSuccessEvent($this->createMock(AuthenticatorInterface::class), new SelfValidatingPassport(new UserBadge('test', function () {})), $token, $this->request, null, 'main_firewall', $previousToken);

        $this->listener->onSuccessfulLogin($event);
    }

    private function createEvent($firewallName)
    {
        return new LoginSuccessEvent($this->createMock(AuthenticatorInterface::class), new SelfValidatingPassport(new UserBadge('test', fn ($username) => new InMemoryUser($username, null))), $this->token, $this->request, null, $firewallName);
    }

    private function configurePreviousSession()
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->any())
            ->method('getName')
            ->willReturn('test_session_name');
        $this->request->setSession($session);
        $this->request->cookies->set('test_session_name', 'session_cookie_val');
    }
}
