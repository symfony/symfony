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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
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
    private TokenStorageInterface $tokenStorage;
    private EventDispatcher $eventDispatcher;
    private Request $request;
    private InMemoryUser $user;
    private TokenInterface $token;
    private Response $response;

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
        $this->eventDispatcher = new EventDispatcher();
        $this->request = new Request();
        $this->user = new InMemoryUser('wouter', null);
        $this->token = new UsernamePasswordToken($this->user, 'main');
        $this->response = new Response();
    }

    #[DataProvider('provideSupportsData')]
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
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $authenticator->method('supports')->willReturn(false);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->never())->method('authenticate');

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateRequest($this->request);
    }

    #[DataProvider('provideMatchingAuthenticatorIndex')]
    public function testAuthenticateRequest($matchingAuthenticatorIndex)
    {
        $matchingAuthenticator = $this->createStub(TestInteractiveAuthenticator::class);
        $matchingAuthenticator->method('supports')->willReturn(true);
        $notMatchingAuthenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $notMatchingAuthenticator->method('supports')->willReturn(false);
        $notMatchingAuthenticator->expects($this->never())->method('authenticate');

        if (0 === $matchingAuthenticatorIndex) {
            $authenticators = [
                $matchingAuthenticator,
                $notMatchingAuthenticator,
            ];
        } else {
            $authenticators = [
                $notMatchingAuthenticator,
                $matchingAuthenticator,
            ];
        }
        $this->request->attributes->set('_security_authenticators', $authenticators);

        $matchingAuthenticator->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));

        $listenerCalled = false;
        $this->eventDispatcher->addListener(CheckPassportEvent::class, function (CheckPassportEvent $event) use (&$listenerCalled, $matchingAuthenticator) {
            if ($event->getAuthenticator() === $matchingAuthenticator && $event->getPassport()->getUser() === $this->user) {
                $listenerCalled = true;
            }
        });
        $matchingAuthenticator->method('createToken')->willReturn($this->token);

        $manager = $this->createManager($authenticators, exposeSecurityErrors: ExposeSecurityLevel::None);
        $this->assertNull($manager->authenticateRequest($this->request));
        $this->assertTrue($listenerCalled, 'The CheckPassportEvent listener is not called');
        $this->assertSame($this->token, $this->tokenStorage->getToken());
    }

    public static function provideMatchingAuthenticatorIndex()
    {
        yield [0];
        yield [1];
    }

    public function testNoCredentialsValidated()
    {
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->method('authenticate')->willReturn(new Passport(new UserBadge('wouter', fn () => $this->user), new PasswordCredentials('pass')));

        $authenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $this->isInstanceOf(BadCredentialsException::class));

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateRequest($this->request);
    }

    public function testRequiredBadgeMissing()
    {
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter')));

        $authenticator->expects($this->once())->method('onAuthenticationFailure')->with($this->anything(), $this->callback(static fn ($exception) => 'Authentication failed; Some badges marked as required by the firewall config are not available on the passport: "'.CsrfTokenBadge::class.'".' === $exception->getMessage()));

        $manager = $this->createManager([$authenticator], 'main', [CsrfTokenBadge::class], exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateRequest($this->request);
    }

    public function testAllRequiredBadgesPresent()
    {
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $csrfBadge = new CsrfTokenBadge('csrfid', 'csrftoken');
        $csrfBadge->markResolved();
        $authenticator->expects($this->any())->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter'), [$csrfBadge]));
        $authenticator->expects($this->any())->method('createToken')->willReturn(new UsernamePasswordToken($this->user, 'main'));

        $authenticator->expects($this->once())->method('onAuthenticationSuccess');

        $manager = $this->createManager([$authenticator], 'main', [CsrfTokenBadge::class], exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateRequest($this->request);
    }

    public function testAuthenticateRequestCanModifyTokenFromEvent()
    {
        $authenticator = $this->createStub(TestInteractiveAuthenticator::class);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));

        $authenticator->method('createToken')->willReturn($this->token);

        $modifiedToken = new UsernamePasswordToken($this->user, 'main');
        $listenerCalled = false;
        $this->eventDispatcher->addListener(AuthenticationTokenCreatedEvent::class, static function (AuthenticationTokenCreatedEvent $event) use (&$listenerCalled, $modifiedToken) {
            $event->setAuthenticatedToken($modifiedToken);
            $listenerCalled = true;
        });

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $this->assertNull($manager->authenticateRequest($this->request));
        $this->assertTrue($listenerCalled, 'The AuthenticationTokenCreatedEvent listener is not called');
        $this->assertSame($modifiedToken, $this->tokenStorage->getToken());
    }

    public function testAuthenticateUser()
    {
        $authenticator = $this->createStub(TestInteractiveAuthenticator::class);
        $authenticator->method('onAuthenticationSuccess')->willReturn($this->response);

        $badge = new UserBadge('alex');

        $authenticator
            ->method('createToken')
            ->willReturnCallback(function (Passport $passport) use ($badge) {
                $this->assertSame(['attr' => 'foo', 'attr2' => 'bar'], $passport->getAttributes());
                $this->assertSame([UserBadge::class => $badge], $passport->getBadges());

                return $this->token;
            });

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateUser($this->user, $authenticator, $this->request, [$badge], ['attr' => 'foo', 'attr2' => 'bar']);

        $this->assertSame($this->token, $this->tokenStorage->getToken());
    }

    public function testAuthenticateUserCanModifyTokenFromEvent()
    {
        $authenticator = $this->createStub(TestInteractiveAuthenticator::class);
        $authenticator->method('createToken')->willReturn($this->token);
        $authenticator->method('onAuthenticationSuccess')->willReturn($this->response);

        $modifiedToken = new UsernamePasswordToken($this->user, 'main');
        $listenerCalled = false;
        $this->eventDispatcher->addListener(AuthenticationTokenCreatedEvent::class, static function (AuthenticationTokenCreatedEvent $event) use (&$listenerCalled, $modifiedToken) {
            $event->setAuthenticatedToken($modifiedToken);
            $listenerCalled = true;
        });

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $manager->authenticateUser($this->user, $authenticator, $this->request);
        $this->assertTrue($listenerCalled, 'The AuthenticationTokenCreatedEvent listener is not called');
        $this->assertSame($modifiedToken, $this->tokenStorage->getToken());
    }

    public function testInteractiveAuthenticator()
    {
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $authenticator->method('isInteractive')->willReturn(true);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));
        $authenticator->method('createToken')->willReturn($this->token);

        $authenticator
            ->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($this->anything(), $this->token, 'main')
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $response = $manager->authenticateRequest($this->request);
        $this->assertSame($this->response, $response);
        $this->assertSame($this->token, $this->tokenStorage->getToken());
    }

    public function testLegacyInteractiveAuthenticator()
    {
        $authenticator = $this->createMock(InteractiveAuthenticatorInterface::class);
        $authenticator->method('isInteractive')->willReturn(true);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));
        $authenticator->method('createToken')->willReturn($this->token);

        $authenticator
            ->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($this->anything(), $this->token, 'main')
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $response = $manager->authenticateRequest($this->request);
        $this->assertSame($this->response, $response);
        $this->assertSame($this->token, $this->tokenStorage->getToken());
    }

    public function testAuthenticateRequestHidesInvalidUserExceptions()
    {
        $invalidUserException = new UserNotFoundException();
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $this->request->attributes->set('_security_authenticators', [$authenticator]);

        $authenticator->method('authenticate')->willThrowException($invalidUserException);

        $authenticator
            ->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->equalTo($this->request), $this->callback(static fn ($e) => $e instanceof BadCredentialsException && $invalidUserException === $e->getPrevious()))
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

        $authenticator->method('authenticate')->willThrowException($invalidUserException);

        $authenticator
            ->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->equalTo($this->request), $this->callback(static fn ($e) => $e === $invalidUserException))
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

        $authenticator->method('authenticate')->willThrowException($invalidUserException);

        $authenticator
            ->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->equalTo($this->request), $this->callback(static fn ($e) => $e instanceof BadCredentialsException && $invalidUserException === $e->getPrevious()))
            ->willReturn($this->response);

        $manager = $this->createManager([$authenticator], exposeSecurityErrors: ExposeSecurityLevel::None);
        $response = $manager->authenticateRequest($this->request);
        $this->assertSame($this->response, $response);
    }

    public function testLogsUseTheDecoratedAuthenticatorWhenItIsTraceable()
    {
        $authenticator = $this->createMock(TestInteractiveAuthenticator::class);
        $authenticator->method('isInteractive')->willReturn(true);
        $this->request->attributes->set('_security_authenticators', [new TraceableAuthenticator($authenticator)]);

        $authenticator->method('authenticate')->willReturn(new SelfValidatingPassport(new UserBadge('wouter', fn () => $this->user)));
        $authenticator->method('createToken')->willReturn($this->token);

        $authenticator
            ->expects($this->once())
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

        $manager = $this->createManager([$authenticator], 'main', [], $logger, exposeSecurityErrors: ExposeSecurityLevel::None);
        $response = $manager->authenticateRequest($this->request);
        $this->assertSame($this->response, $response);
        $this->assertStringContainsString($authenticator::class, $logger->logContexts[0]['authenticator']);
        $this->assertSame($this->token, $this->tokenStorage->getToken());
    }

    private static function createDummySupportsAuthenticator(?bool $supports = true)
    {
        return new DummySupportsAuthenticator($supports);
    }

    private function createManager($authenticators, $firewallName = 'main', array $requiredBadges = [], ?LoggerInterface $logger = null, ExposeSecurityLevel $exposeSecurityErrors = ExposeSecurityLevel::AccountStatus)
    {
        return new AuthenticatorManager($authenticators, $this->tokenStorage, $this->eventDispatcher, $firewallName, $logger, false, $exposeSecurityErrors, $requiredBadges);
    }
}

abstract class TestInteractiveAuthenticator implements InteractiveAuthenticatorInterface
{
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
    }
}
