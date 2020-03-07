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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManager;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\VerifyAuthenticatorCredentialsEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->request = new Request();
        $this->user = $this->createMock(UserInterface::class);
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
        // (which is the case with lazy firewalls and e.g. the AnonymousAuthenticator)
        $authenticator = $this->createAuthenticator(false);
        $this->request->attributes->set('_guard_authenticators', [$authenticator]);

        $authenticator->expects($this->never())->method('getCredentials');

        $manager = $this->createManager([$authenticator]);
        $manager->authenticateRequest($this->request);
    }

    /**
     * @dataProvider provideMatchingAuthenticatorIndex
     */
    public function testAuthenticateRequest($matchingAuthenticatorIndex)
    {
        $authenticators = [$this->createAuthenticator(0 === $matchingAuthenticatorIndex), $this->createAuthenticator(1 === $matchingAuthenticatorIndex)];
        $this->request->attributes->set('_guard_authenticators', $authenticators);
        $matchingAuthenticator = $authenticators[$matchingAuthenticatorIndex];

        $authenticators[($matchingAuthenticatorIndex + 1) % 2]->expects($this->never())->method('getCredentials');

        $matchingAuthenticator->expects($this->any())->method('getCredentials')->willReturn(['password' => 'pa$$']);
        $matchingAuthenticator->expects($this->any())->method('getUser')->willReturn($this->user);
        $this->eventDispatcher->expects($this->exactly(4))
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($matchingAuthenticator) {
                if ($event instanceof VerifyAuthenticatorCredentialsEvent) {
                    return $event->getAuthenticator() === $matchingAuthenticator
                        && $event->getCredentials() === ['password' => 'pa$$']
                        && $event->getUser() === $this->user;
                }

                return $event instanceof InteractiveLoginEvent || $event instanceof LoginSuccessEvent || $event instanceof AuthenticationSuccessEvent;
            }))
            ->will($this->returnCallback(function ($event) {
                if ($event instanceof VerifyAuthenticatorCredentialsEvent) {
                    $event->setCredentialsValid(true);
                }

                return $event;
            }));
        $matchingAuthenticator->expects($this->any())->method('createAuthenticatedToken')->willReturn($this->token);

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->token);

        $matchingAuthenticator->expects($this->any())
            ->method('onAuthenticationSuccess')
            ->with($this->anything(), $this->token, 'main')
            ->willReturn($this->response);

        $manager = $this->createManager($authenticators);
        $this->assertSame($this->response, $manager->authenticateRequest($this->request));
    }

    public function provideMatchingAuthenticatorIndex()
    {
        yield [0];
        yield [1];
    }

    public function testUserNotFound()
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_guard_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('getCredentials')->willReturn(['username' => 'john']);
        $authenticator->expects($this->any())->method('getUser')->with(['username' => 'john'])->willReturn(null);

        $authenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $this->isInstanceOf(UsernameNotFoundException::class));

        $manager = $this->createManager([$authenticator]);
        $manager->authenticateRequest($this->request);
    }

    public function testNoCredentialsValidated()
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_guard_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('getCredentials')->willReturn(['username' => 'john']);
        $authenticator->expects($this->any())->method('getUser')->willReturn($this->user);

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
        $this->request->attributes->set('_guard_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('getCredentials')->willReturn(['username' => 'john']);
        $authenticator->expects($this->any())->method('getUser')->willReturn($this->user);
        $this->eventDispatcher->expects($this->any())
            ->method('dispatch')
            ->will($this->returnCallback(function ($event) {
                if ($event instanceof VerifyAuthenticatorCredentialsEvent) {
                    $event->setCredentialsValid(true);
                }

                return $event;
            }));

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

    public function testAuthenticateUser()
    {
        $authenticator = $this->createAuthenticator();
        $authenticator->expects($this->any())->method('createAuthenticatedToken')->willReturn($this->token);
        $authenticator->expects($this->any())->method('onAuthenticationSuccess')->willReturn($this->response);

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->token);

        $manager = $this->createManager([$authenticator]);
        $this->assertSame($this->response, $manager->authenticateUser($this->user, $authenticator, $this->request));
    }

    private function createAuthenticator($supports = true)
    {
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $authenticator->expects($this->any())->method('supports')->willReturn($supports);

        return $authenticator;
    }

    private function createManager($authenticators, $providerKey = 'main', $eraseCredentials = true)
    {
        return new AuthenticatorManager($authenticators, $this->tokenStorage, $this->eventDispatcher, $providerKey, null, $eraseCredentials);
    }
}
