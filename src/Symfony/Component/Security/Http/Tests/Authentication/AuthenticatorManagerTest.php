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
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManager;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
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
        $this->tokenStorage = self::createMock(TokenStorageInterface::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->request = new Request();
        $this->user = new InMemoryUser('wouter', null);
        $this->token = self::createMock(TokenInterface::class);
        $this->token->expects(self::any())->method('getUser')->willReturn($this->user);
        $this->response = self::createMock(Response::class);
    }

    /**
     * @dataProvider provideSupportsData
     */
    public function testSupports($authenticators, $result)
    {
        $manager = $this->createManager($authenticators);

        self::assertEquals($result, $manager->supports($this->request));
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

        $authenticator->expects(self::never())->method('authenticate');

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

        $authenticators[($matchingAuthenticatorIndex + 1) % 2]->expects(self::never())->method('authenticate');

        $matchingAuthenticator->expects(self::any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));

        $listenerCalled = false;
        $this->eventDispatcher->addListener(CheckPassportEvent::class, function (CheckPassportEvent $event) use (&$listenerCalled, $matchingAuthenticator) {
            if ($event->getAuthenticator() === $matchingAuthenticator && $event->getPassport()->getUser() === $this->user) {
                $listenerCalled = true;
            }
        });
        $matchingAuthenticator->expects(self::any())->method('createToken')->willReturn($this->token);

        $this->tokenStorage->expects(self::once())->method('setToken')->with($this->token);

        $manager = $this->createManager($authenticators);
        self::assertNull($manager->authenticateRequest($this->request));
        self::assertTrue($listenerCalled, 'The CheckPassportEvent listener is not called');
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

        $authenticator->expects(self::any())->method('authenticate')->willReturn(new Passport(new UserBadge('wouter', function () { return $this->user; }), new PasswordCredentials('pass')));

        $authenticator->expects(self::once())
            ->method('onAuthenticationFailure')
            ->with($this->request, self::isInstanceOf(BadCredentialsException::class));

        $manager = $this->createManager([$authenticator]);
        $manager->authenticateRequest($this->request);
    }

    public function testRequiredBadgeMissing()
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects(self::any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter')));

        $authenticator->expects(self::once())->method('onAuthenticationFailure')->with(self::anything(), self::callback(function ($exception) {
            return 'Authentication failed; Some badges marked as required by the firewall config are not available on the passport: "'.CsrfTokenBadge::class.'".' === $exception->getMessage();
        }));

        $manager = $this->createManager([$authenticator], 'main', true, [CsrfTokenBadge::class]);
        $manager->authenticateRequest($this->request);
    }

    public function testAllRequiredBadgesPresent()
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $csrfBadge = new CsrfTokenBadge('csrfid', 'csrftoken');
        $csrfBadge->markResolved();
        $authenticator->expects(self::any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter'), [$csrfBadge]));
        $authenticator->expects(self::any())->method('createToken')->willReturn(new UsernamePasswordToken($this->user, 'main'));

        $authenticator->expects(self::once())->method('onAuthenticationSuccess');

        $manager = $this->createManager([$authenticator], 'main', true, [CsrfTokenBadge::class]);
        $manager->authenticateRequest($this->request);
    }

    /**
     * @dataProvider provideEraseCredentialsData
     */
    public function testEraseCredentials($eraseCredentials)
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects(self::any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));

        $authenticator->expects(self::any())->method('createToken')->willReturn($this->token);

        $this->token->expects($eraseCredentials ? self::once() : self::never())->method('eraseCredentials');

        $manager = $this->createManager([$authenticator], 'main', $eraseCredentials);
        $manager->authenticateRequest($this->request);
    }

    public function provideEraseCredentialsData()
    {
        yield [true];
        yield [false];
    }

    public function testAuthenticateRequestCanModifyTokenFromEvent()
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects(self::any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));

        $authenticator->expects(self::any())->method('createToken')->willReturn($this->token);

        $modifiedToken = self::createMock(TokenInterface::class);
        $modifiedToken->expects(self::any())->method('getUser')->willReturn($this->user);
        $listenerCalled = false;
        $this->eventDispatcher->addListener(AuthenticationTokenCreatedEvent::class, function (AuthenticationTokenCreatedEvent $event) use (&$listenerCalled, $modifiedToken) {
            $event->setAuthenticatedToken($modifiedToken);
            $listenerCalled = true;
        });

        $this->tokenStorage->expects(self::once())->method('setToken')->with(self::identicalTo($modifiedToken));

        $manager = $this->createManager([$authenticator]);
        self::assertNull($manager->authenticateRequest($this->request));
        self::assertTrue($listenerCalled, 'The AuthenticationTokenCreatedEvent listener is not called');
    }

    public function testAuthenticateUser()
    {
        $authenticator = $this->createAuthenticator();
        $authenticator->expects(self::any())->method('createToken')->willReturn($this->token);
        $authenticator->expects(self::any())->method('onAuthenticationSuccess')->willReturn($this->response);

        $this->tokenStorage->expects(self::once())->method('setToken')->with($this->token);

        $manager = $this->createManager([$authenticator]);
        $manager->authenticateUser($this->user, $authenticator, $this->request);
    }

    public function testAuthenticateUserCanModifyTokenFromEvent()
    {
        $authenticator = $this->createAuthenticator();
        $authenticator->expects(self::any())->method('createToken')->willReturn($this->token);
        $authenticator->expects(self::any())->method('onAuthenticationSuccess')->willReturn($this->response);

        $modifiedToken = self::createMock(TokenInterface::class);
        $modifiedToken->expects(self::any())->method('getUser')->willReturn($this->user);
        $listenerCalled = false;
        $this->eventDispatcher->addListener(AuthenticationTokenCreatedEvent::class, function (AuthenticationTokenCreatedEvent $event) use (&$listenerCalled, $modifiedToken) {
            $event->setAuthenticatedToken($modifiedToken);
            $listenerCalled = true;
        });

        $this->tokenStorage->expects(self::once())->method('setToken')->with(self::identicalTo($modifiedToken));

        $manager = $this->createManager([$authenticator]);
        $manager->authenticateUser($this->user, $authenticator, $this->request);
        self::assertTrue($listenerCalled, 'The AuthenticationTokenCreatedEvent listener is not called');
    }

    public function testInteractiveAuthenticator()
    {
        $authenticator = self::createMock(TestInteractiveAuthenticator::class);
        $authenticator->expects(self::any())->method('isInteractive')->willReturn(true);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects(self::any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));
        $authenticator->expects(self::any())->method('createToken')->willReturn($this->token);

        $this->tokenStorage->expects(self::once())->method('setToken')->with($this->token);

        $authenticator->expects(self::any())
            ->method('onAuthenticationSuccess')
            ->with(self::anything(), $this->token, 'main')
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator]);
        $response = $manager->authenticateRequest($this->request);
        self::assertSame($this->response, $response);
    }

    public function testLegacyInteractiveAuthenticator()
    {
        $authenticator = self::createMock(InteractiveAuthenticatorInterface::class);
        $authenticator->expects(self::any())->method('isInteractive')->willReturn(true);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects(self::any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));
        $authenticator->expects(self::any())->method('createAuthenticatedToken')->willReturn($this->token);

        $this->tokenStorage->expects(self::once())->method('setToken')->with($this->token);

        $authenticator->expects(self::any())
            ->method('onAuthenticationSuccess')
            ->with(self::anything(), $this->token, 'main')
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator]);
        $response = $manager->authenticateRequest($this->request);
        self::assertSame($this->response, $response);
    }

    public function testAuthenticateRequestHidesInvalidUserExceptions()
    {
        $invalidUserException = new UserNotFoundException();
        $authenticator = self::createMock(TestInteractiveAuthenticator::class);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects(self::any())->method('authenticate')->willThrowException($invalidUserException);

        $authenticator->expects(self::any())
            ->method('onAuthenticationFailure')
            ->with(self::equalTo($this->request), self::callback(function ($e) use ($invalidUserException) {
                return $e instanceof BadCredentialsException && $invalidUserException === $e->getPrevious();
            }))
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator]);
        $response = $manager->authenticateRequest($this->request);
        self::assertSame($this->response, $response);
    }

    public function testLogsUseTheDecoratedAuthenticatorWhenItIsTraceable()
    {
        $authenticator = self::createMock(TestInteractiveAuthenticator::class);
        $authenticator->expects(self::any())->method('isInteractive')->willReturn(true);
        $this->request->attributes->set('_security_authenticators', [new TraceableAuthenticator($authenticator)]);

        $authenticator->expects(self::any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', function () { return $this->user; })));
        $authenticator->expects(self::any())->method('createToken')->willReturn($this->token);

        $this->tokenStorage->expects(self::once())->method('setToken')->with($this->token);

        $authenticator->expects(self::any())
            ->method('onAuthenticationSuccess')
            ->with(self::anything(), $this->token, 'main')
            ->willReturn($this->response);

        $authenticator->expects(self::any())
            ->method('onAuthenticationSuccess')
            ->with(self::anything(), $this->token, 'main')
            ->willReturn($this->response);

        $logger = new class() extends AbstractLogger {
            public $logContexts = [];

            public function log($level, $message, array $context = []): void
            {
                if ($context['authenticator'] ?? false) {
                    $this->logContexts[] = $context;
                }
            }
        };

        $manager = $this->createManager([$authenticator], 'main', true, [], $logger);
        $response = $manager->authenticateRequest($this->request);
        self::assertSame($this->response, $response);
        self::assertStringContainsString('Mock_TestInteractiveAuthenticator', $logger->logContexts[0]['authenticator']);
    }

    private function createAuthenticator($supports = true)
    {
        $authenticator = self::createMock(TestInteractiveAuthenticator::class);
        $authenticator->expects(self::any())->method('supports')->willReturn($supports);

        return $authenticator;
    }

    private function createManager($authenticators, $firewallName = 'main', $eraseCredentials = true, array $requiredBadges = [], LoggerInterface $logger = null)
    {
        return new AuthenticatorManager($authenticators, $this->tokenStorage, $this->eventDispatcher, $firewallName, $logger, $eraseCredentials, true, $requiredBadges);
    }
}

abstract class TestInteractiveAuthenticator implements InteractiveAuthenticatorInterface
{
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
    }
}
