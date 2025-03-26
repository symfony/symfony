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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManager;
use Symfony\Component\Security\Http\Authentication\ExposeSecurityLevel;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Tests\Fixtures\DummySupportsAuthenticator;

class AuthenticatorManagerTest extends TestCase
{
    use ExpectDeprecationTrait;

    private MockObject&TokenStorageInterface $tokenStorage;
    private EventDispatcher $eventDispatcher;
    private Request $request;
    private InMemoryUser $user;
    private MockObject&TokenInterface $token;
    private Response $response;

    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->request = new Request();
        $this->user = new InMemoryUser('wouter', null);
        $this->token = $this->createMock(TokenInterface::class);
        $this->token->expects($this->any())->method('getUser')->willReturn($this->user);
        $this->response = $this->createMock(Response::class);
    }

    /**
     * @dataProvider provideSupportsData
     */
    public function testSupports($authenticators, $result)
    {
        $manager = $this->createManager($authenticators, exposeSecurityErrors: ExposeSecurityLevel::None);

        $this->assertEquals($result, $manager->supports($this->request));
    }

    public static function provideSupportsData()
    {
        yield [[self::createDummySupportsAuthenticator(null), self::createDummySupportsAuthenticator(null)], null];
        yield [[self::createDummySupportsAuthenticator(null), self::createDummySupportsAuthenticator(false)], null];

        yield [[self::createDummySupportsAuthenticator(null), self::createDummySupportsAuthenticator(true)], true];
        yield [[self::createDummySupportsAuthenticator(true), self::createDummySupportsAuthenticator(false)], true];

        yield [[self::createDummySupportsAuthenticator(false), self::createDummySupportsAuthenticator(false)], false];
        yield [[], false];
    }

    public function testSupportsInvalidAuthenticator()
    {
        $manager = $this->createManager([new \stdClass()], exposeSecurityErrors: ExposeSecurityLevel::None);

        $this->expectExceptionObject(
            new \InvalidArgumentException('Authenticator "stdClass" must implement "Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface".')
        );

        $manager->supports($this->request);
    }

    public function testSupportCheckedUponRequestAuthentication()
    {
        // the attribute stores the supported authenticators, returning false now
        // means support changed between calling supports() and authenticateRequest()
        // (which is the case with lazy firewalls)
        $authenticator = $this->createAuthenticator(false);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->never())->method('authenticate');

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
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

        $matchingAuthenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));

        $listenerCalled = false;
        $this->eventDispatcher->addListener(CheckPassportEvent::class, function (CheckPassportEvent $event) use (&$listenerCalled, $matchingAuthenticator) {
            if ($event->getAuthenticator() === $matchingAuthenticator && $event->getPassport()->getUser() === $this->user) {
                $listenerCalled = true;
            }
        });
        $matchingAuthenticator->expects($this->any())->method('createToken')->willReturn($this->token);

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->token);

        $manager = $this->createManager($authenticators, exposeSecurityErrors: ExposeSecurityLevel::None);
        $this->assertNull($manager->authenticateRequest($this->request));
        $this->assertTrue($listenerCalled, 'The CheckPassportEvent listener is not called');
    }

    public static function provideMatchingAuthenticatorIndex()
    {
        yield [0];
        yield [1];
    }

    public function testNoCredentialsValidated()
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new Passport(new UserBadge('wouter', fn () => $this->user), new PasswordCredentials('pass')));

        $authenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $this->isInstanceOf(BadCredentialsException::class));

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateRequest($this->request);
    }

    public function testRequiredBadgeMissing()
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter')));

        $authenticator->expects($this->once())->method('onAuthenticationFailure')->with($this->anything(), $this->callback(fn ($exception) => 'Authentication failed; Some badges marked as required by the firewall config are not available on the passport: "'.CsrfTokenBadge::class.'".' === $exception->getMessage()));

        $manager = $this->createManager([$authenticator], 'main', true, [CsrfTokenBadge::class], exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateRequest($this->request);
    }

    public function testAllRequiredBadgesPresent()
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $csrfBadge = new CsrfTokenBadge('csrfid', 'csrftoken');
        $csrfBadge->markResolved();
        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter'), [$csrfBadge]));
        $authenticator->expects($this->any())->method('createToken')->willReturn(new UsernamePasswordToken($this->user, 'main'));

        $authenticator->expects($this->once())->method('onAuthenticationSuccess');

        $manager = $this->createManager([$authenticator], 'main', true, [CsrfTokenBadge::class], exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateRequest($this->request);
    }

    /**
     * @group legacy
     *
     * @dataProvider provideEraseCredentialsData
     */
    public function testEraseCredentials($eraseCredentials)
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));

        $token = new class extends AbstractToken {
            public $erased = false;

            public function eraseCredentials(): void
            {
                $this->erased = true;
            }
        };

        $authenticator->expects($this->any())->method('createToken')->willReturn($token);

        if ($eraseCredentials) {
            $this->expectDeprecation(\sprintf('Since symfony/security-http 7.3: Implementing "%s@anonymous::eraseCredentials()" is deprecated since Symfony 7.3; add the #[\Deprecated] attribute on the method to signal its either empty or that you moved the logic elsewhere, typically to the "__serialize()" method.', AbstractToken::class));
        }

        $manager = $this->createManager([$authenticator], 'main', $eraseCredentials, exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateRequest($this->request);

        $this->assertSame($eraseCredentials, $token->erased);
    }

    public static function provideEraseCredentialsData()
    {
        yield [true];
        yield [false];
    }

    public function testAuthenticateRequestCanModifyTokenFromEvent()
    {
        $authenticator = $this->createAuthenticator();
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));

        $authenticator->expects($this->any())->method('createToken')->willReturn($this->token);

        $modifiedToken = $this->createMock(TokenInterface::class);
        $modifiedToken->expects($this->any())->method('getUser')->willReturn($this->user);
        $listenerCalled = false;
        $this->eventDispatcher->addListener(AuthenticationTokenCreatedEvent::class, function (AuthenticationTokenCreatedEvent $event) use (&$listenerCalled, $modifiedToken) {
            $event->setAuthenticatedToken($modifiedToken);
            $listenerCalled = true;
        });

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->identicalTo($modifiedToken));

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $this->assertNull($manager->authenticateRequest($this->request));
        $this->assertTrue($listenerCalled, 'The AuthenticationTokenCreatedEvent listener is not called');
    }

    public function testAuthenticateUser()
    {
        $authenticator = $this->createAuthenticator();
        $authenticator->expects($this->any())->method('onAuthenticationSuccess')->willReturn($this->response);

        $badge = new UserBadge('alex');

        $authenticator
            ->expects($this->any())
            ->method('createToken')
            ->willReturnCallback(function (Passport $passport) use ($badge) {
                $this->assertSame(['attr' => 'foo', 'attr2' => 'bar'], $passport->getAttributes());
                $this->assertSame([UserBadge::class => $badge], $passport->getBadges());

                return $this->token;
            });

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->token);

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateUser($this->user, $authenticator, $this->request, [$badge], ['attr' => 'foo', 'attr2' => 'bar']);
    }

    public function testAuthenticateUserCanModifyTokenFromEvent()
    {
        $authenticator = $this->createAuthenticator();
        $authenticator->expects($this->any())->method('createToken')->willReturn($this->token);
        $authenticator->expects($this->any())->method('onAuthenticationSuccess')->willReturn($this->response);

        $modifiedToken = $this->createMock(TokenInterface::class);
        $modifiedToken->expects($this->any())->method('getUser')->willReturn($this->user);
        $listenerCalled = false;
        $this->eventDispatcher->addListener(AuthenticationTokenCreatedEvent::class, function (AuthenticationTokenCreatedEvent $event) use (&$listenerCalled, $modifiedToken) {
            $event->setAuthenticatedToken($modifiedToken);
            $listenerCalled = true;
        });

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->identicalTo($modifiedToken));

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateUser($this->user, $authenticator, $this->request);
        $this->assertTrue($listenerCalled, 'The AuthenticationTokenCreatedEvent listener is not called');
    }

    public function testInteractiveAuthenticator()
    {
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $authenticator->expects($this->any())->method('isInteractive')->willReturn(true);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));
        $authenticator->expects($this->any())->method('createToken')->willReturn($this->token);

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->token);

        $authenticator->expects($this->any())
            ->method('onAuthenticationSuccess')
            ->with($this->anything(), $this->token, 'main')
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $response = $manager->authenticateRequest($this->request);
        $this->assertSame($this->response, $response);
    }

    public function testLegacyInteractiveAuthenticator()
    {
        $authenticator = $this->createMock(InteractiveAuthenticatorInterface::class);
        $authenticator->expects($this->any())->method('isInteractive')->willReturn(true);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));
        $authenticator->expects($this->any())->method('createToken')->willReturn($this->token);

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->token);

        $authenticator->expects($this->any())
            ->method('onAuthenticationSuccess')
            ->with($this->anything(), $this->token, 'main')
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $response = $manager->authenticateRequest($this->request);
        $this->assertSame($this->response, $response);
    }

    public function testAuthenticateRequestHidesInvalidUserExceptions()
    {
        $invalidUserException = new UserNotFoundException();
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willThrowException($invalidUserException);

        $authenticator->expects($this->any())
            ->method('onAuthenticationFailure')
            ->with($this->equalTo($this->request), $this->callback(fn ($e) => $e instanceof BadCredentialsException && $invalidUserException === $e->getPrevious()))
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $response = $manager->authenticateRequest($this->request);
        $this->assertSame($this->response, $response);
    }

    public function testAuthenticateRequestShowsAccountStatusException()
    {
        $invalidUserException = new LockedException();
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willThrowException($invalidUserException);

        $authenticator->expects($this->any())
            ->method('onAuthenticationFailure')
            ->with($this->equalTo($this->request), $this->callback(fn ($e) => $e === $invalidUserException))
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::AccountStatus);
        $response = $manager->authenticateRequest($this->request);
        $this->assertSame($this->response, $response);
    }

    public function testAuthenticateRequestHidesInvalidAccountStatusException()
    {
        $invalidUserException = new LockedException();
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willThrowException($invalidUserException);

        $authenticator->expects($this->any())
            ->method('onAuthenticationFailure')
            ->with($this->equalTo($this->request), $this->callback(fn ($e) => $e instanceof BadCredentialsException && $invalidUserException === $e->getPrevious()))
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $response = $manager->authenticateRequest($this->request);
        $this->assertSame($this->response, $response);
    }

    public function testLogsUseTheDecoratedAuthenticatorWhenItIsTraceable()
    {
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $authenticator->expects($this->any())->method('isInteractive')->willReturn(true);
        $this->request->attributes->set('_security_authenticators', [new TraceableAuthenticator($authenticator)]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));
        $authenticator->expects($this->any())->method('createToken')->willReturn($this->token);

        $this->tokenStorage->expects($this->once())->method('setToken')->with($this->token);

        $authenticator->expects($this->any())
            ->method('onAuthenticationSuccess')
            ->with($this->anything(), $this->token, 'main')
            ->willReturn($this->response);

        $authenticator->expects($this->any())
            ->method('onAuthenticationSuccess')
            ->with($this->anything(), $this->token, 'main')
            ->willReturn($this->response);

        $logger = new class extends AbstractLogger {
            public array $logContexts = [];

            public function log($level, $message, array $context = []): void
            {
                if ($context['authenticator'] ?? false) {
                    $this->logContexts[] = $context;
                }
            }
        };

        $manager = $this->createManager([$authenticator], 'main', false, [], $logger, exposeSecurityErrors: ExposeSecurityLevel::None);
        $response = $manager->authenticateRequest($this->request);
        $this->assertSame($this->response, $response);
        $this->assertStringContainsString($authenticator::class, $logger->logContexts[0]['authenticator']);
    }

    private function createAuthenticator(?bool $supports = true)
    {
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $authenticator->expects($this->any())->method('supports')->willReturn($supports);

        return $authenticator;
    }

    private static function createDummySupportsAuthenticator(?bool $supports = true)
    {
        return new DummySupportsAuthenticator($supports);
    }

    private function createManager($authenticators, $firewallName = 'main', $eraseCredentials = false, array $requiredBadges = [], ?LoggerInterface $logger = null, ExposeSecurityLevel $exposeSecurityErrors = ExposeSecurityLevel::AccountStatus)
    {
        return new AuthenticatorManager($authenticators, $this->tokenStorage, $this->eventDispatcher, $firewallName, $logger, $eraseCredentials, $exposeSecurityErrors, $requiredBadges);
    }
}

abstract class TestInteractiveAuthenticator implements InteractiveAuthenticatorInterface
{
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
    }
}
