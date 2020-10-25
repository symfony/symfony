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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManager;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

class AuthenticatorManagerTest extends TestCase
{
    private $tokenStorage;
    private $eventDispatcher;
    private $request;
    private $user;
    private $token;
    private $response;

    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->request = new Request();
        $this->user = new User('wouter', null);
        $this->token = $this->createMock(TokenInterface::class);
        $this->response = $this->createMock(Response::class);
    }

    /**
     * @dataProvider provideSupportsData
     */
    public function testSupports($authenticators, $result)
    {
        $manager = $this->createManager($authenticators);

        $this->assertEquals($result, $manager->supports($this->request));
    }

    public function provideSupportsData()
    {
        yield [[$this->createAuthenticator(null), $this->createAuthenticator(null)], null];
        yield [[$this->createAuthenticator(null), $this->createAuthenticator(false)], null];

        yield [[$this->createAuthenticator(null), $this->createAuthenticator(true)], true];
        yield [[$this->createAuthenticator(true), $this->createAuthenticator(false)], true];

        yield [[$this->createAuthenticator(false), $this->createAuthenticator(false)], false];
        yield [[], false];
    }

    public function testSupportCheckedUponRequestAuthentication()
    {
        // the attribute stores the supported authenticators, returning false now
        // means support changed between calling supports() and authenticateRequest()
        // (which is the case with lazy firewalls)
        $authenticator = $this->createAuthenticator(false);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->never())->method('authenticate');

        $manager = $this->createManager([$authenticator]);
        $manager->authenticateRequest($this->request);
    }

    /**
     * @dataProvider provideMatchingAuthenticatorIndex
     */
    public function testAuthenticateRequest($matchingAuthenticatorIndex)
    {
        $authenticators = [$this->createAuthenticator(0 === $matchingAuthenticatorIndex), $this->createAuthenticator(1 === $matchingAuthenticatorIndex)];
        $this->request->attributes->set('_security_authenticators', $authenticators);
        $matchingAuthenticator = $authenticators[$matchingAuthenticatorIndex];

        $authenticators[($matchingAuthenticatorIndex + 1) % 2]->expects($this->never())->method('authenticate');

        $matchingAuthenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));

        $listenerCalled = false;
        $this->eventDispatcher->addListener(CheckPassportEvent::class, function (CheckPassportEvent $event) use (&$listenerCalled, $matchingAuthenticator) {
            if ($event->getAuthenticator() === $matchingAuthenticator && $event->getPassport()->getUser() === $this->user) {
                $listenerCalled = true;
            }
        });
        $matchingAuthenticator->expects($this->any())->method('createAuthenticatedToken')->willReturn($this->token);

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->token);

        $manager = $this->createManager($authenticators);
        $this->assertNull($manager->authenticateRequest($this->request));
        $this->assertTrue($listenerCalled, 'The CheckPassportEvent listener is not called');
    }

    public function provideMatchingAuthenticatorIndex()
    {
        yield [0];
        yield [1];
    }

    public function testNoCredentialsValidated()
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new Passport(new UserBadge('wouter', function () { return $this->user; }), new PasswordCredentials('pass')));

        $authenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $this->isInstanceOf(BadCredentialsException::class));

        $manager = $this->createManager([$authenticator]);
        $manager->authenticateRequest($this->request);
    }

    /**
     * @dataProvider provideEraseCredentialsData
     */
    public function testEraseCredentials($eraseCredentials)
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));

        $authenticator->expects($this->any())->method('createAuthenticatedToken')->willReturn($this->token);

        $this->token->expects($eraseCredentials ? $this->once() : $this->never())->method('eraseCredentials');

        $manager = $this->createManager([$authenticator], 'main', $eraseCredentials);
        $manager->authenticateRequest($this->request);
    }

    public function provideEraseCredentialsData()
    {
        yield [true];
        yield [false];
    }

    public function testAuthenticateRequestCanModifyTokenFromEvent(): void
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));

        $authenticator->expects($this->any())->method('createAuthenticatedToken')->willReturn($this->token);

        $modifiedToken = $this->createMock(TokenInterface::class);
        $listenerCalled = false;
        $this->eventDispatcher->addListener(AuthenticationTokenCreatedEvent::class, function (AuthenticationTokenCreatedEvent $event) use (&$listenerCalled, $modifiedToken) {
            $event->setAuthenticatedToken($modifiedToken);
            $listenerCalled = true;
        });

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->identicalTo($modifiedToken));

        $manager = $this->createManager([$authenticator]);
        $this->assertNull($manager->authenticateRequest($this->request));
        $this->assertTrue($listenerCalled, 'The AuthenticationTokenCreatedEvent listener is not called');
    }

    public function testAuthenticateUser()
    {
        $authenticator = $this->createAuthenticator();
        $authenticator->expects($this->any())->method('createAuthenticatedToken')->willReturn($this->token);
        $authenticator->expects($this->any())->method('onAuthenticationSuccess')->willReturn($this->response);

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->token);

        $manager = $this->createManager([$authenticator]);
        $manager->authenticateUser($this->user, $authenticator, $this->request);
    }

    public function testAuthenticateUserCanModifyTokenFromEvent(): void
    {
        $authenticator = $this->createAuthenticator();
        $authenticator->expects($this->any())->method('createAuthenticatedToken')->willReturn($this->token);
        $authenticator->expects($this->any())->method('onAuthenticationSuccess')->willReturn($this->response);

        $modifiedToken = $this->createMock(TokenInterface::class);
        $listenerCalled = false;
        $this->eventDispatcher->addListener(AuthenticationTokenCreatedEvent::class, function (AuthenticationTokenCreatedEvent $event) use (&$listenerCalled, $modifiedToken) {
            $event->setAuthenticatedToken($modifiedToken);
            $listenerCalled = true;
        });

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->identicalTo($modifiedToken));

        $manager = $this->createManager([$authenticator]);
        $manager->authenticateUser($this->user, $authenticator, $this->request);
        $this->assertTrue($listenerCalled, 'The AuthenticationTokenCreatedEvent listener is not called');
    }

    public function testInteractiveAuthenticator()
    {
        $authenticator = $this->createMock(InteractiveAuthenticatorInterface::class);
        $authenticator->expects($this->any())->method('isInteractive')->willReturn(true);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));
        $authenticator->expects($this->any())->method('createAuthenticatedToken')->willReturn($this->token);

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->token);

        $authenticator->expects($this->any())
            ->method('onAuthenticationSuccess')
            ->with($this->anything(), $this->token, 'main')
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator]);
        $response = $manager->authenticateRequest($this->request);
        $this->assertSame($this->response, $response);
    }

    private function createAuthenticator($supports = true)
    {
        $authenticator = $this->createMock(InteractiveAuthenticatorInterface::class);
        $authenticator->expects($this->any())->method('supports')->willReturn($supports);

        return $authenticator;
    }

    private function createManager($authenticators, $firewallName = 'main', $eraseCredentials = true)
    {
        return new AuthenticatorManager($authenticators, $this->tokenStorage, $this->eventDispatcher, $firewallName, null, $eraseCredentials);
    }
}
