<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\ProviderNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * @group legacy
 */
class AuthenticationProviderManagerTest extends TestCase
{
    public function testAuthenticateWithoutProviders()
    {
        self::expectException(\InvalidArgumentException::class);
        new AuthenticationProviderManager([]);
    }

    public function testAuthenticateWithProvidersWithIncorrectInterface()
    {
        self::expectException(\InvalidArgumentException::class);
        (new AuthenticationProviderManager([
            new \stdClass(),
        ]))->authenticate(self::createMock(TokenInterface::class));
    }

    public function testAuthenticateWhenNoProviderSupportsToken()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(false),
        ]);

        try {
            $manager->authenticate($token = self::createMock(TokenInterface::class));
            self::fail();
        } catch (ProviderNotFoundException $e) {
            self::assertSame($token, $e->getToken());
        }
    }

    public function testAuthenticateWhenProviderReturnsAccountStatusException()
    {
        $secondAuthenticationProvider = self::createMock(AuthenticationProviderInterface::class);

        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, AccountStatusException::class),
            $secondAuthenticationProvider,
        ]);

        // AccountStatusException stops authentication
        $secondAuthenticationProvider->expects(self::never())->method('supports');

        try {
            $manager->authenticate($token = self::createMock(TokenInterface::class));
            self::fail();
        } catch (AccountStatusException $e) {
            self::assertSame($token, $e->getToken());
        }
    }

    public function testAuthenticateWhenProviderReturnsAuthenticationException()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, AuthenticationException::class),
        ]);

        try {
            $manager->authenticate($token = self::createMock(TokenInterface::class));
            self::fail();
        } catch (AuthenticationException $e) {
            self::assertSame($token, $e->getToken());
        }
    }

    public function testAuthenticateWhenOneReturnsAuthenticationExceptionButNotAll()
    {
        $expected = self::createMock(TokenInterface::class);
        $expected->expects(self::any())->method('getUser')->willReturn(new InMemoryUser('wouter', null));

        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, AuthenticationException::class),
            $this->getAuthenticationProvider(true, $expected),
        ]);

        $token = $manager->authenticate(self::createMock(TokenInterface::class));
        self::assertSame($expected, $token);
    }

    public function testAuthenticateReturnsTokenOfTheFirstMatchingProvider()
    {
        $second = self::createMock(AuthenticationProviderInterface::class);
        $second
            ->expects(self::never())
            ->method('supports')
        ;
        $expected = self::createMock(TokenInterface::class);
        $expected->expects(self::any())->method('getUser')->willReturn(new InMemoryUser('wouter', null));
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $expected),
            $second,
        ]);

        $token = $manager->authenticate(self::createMock(TokenInterface::class));
        self::assertSame($expected, $token);
    }

    public function testEraseCredentialFlag()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar', 'key')),
        ]);

        $token = $manager->authenticate(self::createMock(TokenInterface::class));
        self::assertEquals('', $token->getCredentials());

        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar', 'key')),
        ], false);

        $token = $manager->authenticate(self::createMock(TokenInterface::class));
        self::assertEquals('bar', $token->getCredentials());
    }

    public function testAuthenticateDispatchesAuthenticationFailureEvent()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');
        $provider = self::createMock(AuthenticationProviderInterface::class);
        $provider->expects(self::once())->method('supports')->willReturn(true);
        $provider->expects(self::once())->method('authenticate')->willThrowException($exception = new AuthenticationException());

        $dispatcher = self::createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::equalTo(new AuthenticationFailureEvent($token, $exception)), AuthenticationEvents::AUTHENTICATION_FAILURE);

        $manager = new AuthenticationProviderManager([$provider]);
        $manager->setEventDispatcher($dispatcher);

        try {
            $manager->authenticate($token);
            self::fail('->authenticate() should rethrow exceptions');
        } catch (AuthenticationException $e) {
            self::assertSame($token, $exception->getToken());
        }
    }

    public function testAuthenticateDispatchesAuthenticationSuccessEvent()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');

        $provider = self::createMock(AuthenticationProviderInterface::class);
        $provider->expects(self::once())->method('supports')->willReturn(true);
        $provider->expects(self::once())->method('authenticate')->willReturn($token);

        $dispatcher = self::createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::equalTo(new AuthenticationSuccessEvent($token)), AuthenticationEvents::AUTHENTICATION_SUCCESS);

        $manager = new AuthenticationProviderManager([$provider]);
        $manager->setEventDispatcher($dispatcher);

        self::assertSame($token, $manager->authenticate($token));
    }

    protected function getAuthenticationProvider($supports, $token = null, $exception = null)
    {
        $provider = self::createMock(AuthenticationProviderInterface::class);
        $provider->expects(self::once())
                 ->method('supports')
                 ->willReturn($supports)
        ;

        if (null !== $token) {
            $provider->expects(self::once())
                     ->method('authenticate')
                     ->willReturn($token)
            ;
        } elseif (null !== $exception) {
            $provider->expects(self::once())
                     ->method('authenticate')
                     ->willThrowException(self::getMockBuilder($exception)->setMethods(null)->getMock())
            ;
        }

        return $provider;
    }
}
