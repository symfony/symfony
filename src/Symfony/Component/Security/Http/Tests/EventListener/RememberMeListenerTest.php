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
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\EventListener\RememberMeListener;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;

class RememberMeListenerTest extends TestCase
{
    private $rememberMeHandler;
    private $listener;
    private $request;
    private $response;

    protected function setUp(): void
    {
        $this->rememberMeHandler = $this->createMock(RememberMeHandlerInterface::class);
        $this->listener = new RememberMeListener($this->rememberMeHandler);
        $this->request = Request::create('/login');
        $this->request->request->set('_remember_me', true);
        $this->response = new Response();
    }

    public function testSuccessfulLoginWithoutSupportingAuthenticator()
    {
        $this->rememberMeHandler->expects($this->never())->method('createRememberMeCookie');

        $event = $this->createLoginSuccessfulEvent($this->createPassport([]));
        $this->listener->onSuccessfulLogin($event);
    }

    public function testSuccessfulLoginWithRememberMeDisabled()
    {
        $this->rememberMeHandler->expects($this->never())->method('createRememberMeCookie');

        $event = $this->createLoginSuccessfulEvent($this->createPassport([new RememberMeBadge()]));
        $this->listener->onSuccessfulLogin($event);
    }

    public function testCredentialsInvalid()
    {
        $this->rememberMeHandler->expects($this->once())->method('clearRememberMeCookie');

        $this->listener->clearCookie();
    }

    private function createLoginSuccessfulEvent(Passport $passport = null)
    {
        $passport ??= $this->createPassport();

        return new LoginSuccessEvent($this->createMock(AuthenticatorInterface::class), $passport, $this->createMock(TokenInterface::class), $this->request, $this->response, 'main_firewall');
    }

    private function createPassport(array $badges = null)
    {
        if (null === $badges) {
            $badge = new RememberMeBadge();
            $badge->enable();
            $badges = [$badge];
        }

        return new SelfValidatingPassport(new UserBadge('test', fn ($username) => new InMemoryUser($username, null)), $badges);
    }
}
