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
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\ProviderNotFoundException;
use Symfony\Component\Security\Core\Tests\Fixtures\TokenInterface;

class AuthenticationProviderManagerTest extends TestCase
{
    public function testAuthenticateWithoutProviders()
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuthenticationProviderManager([]);
    }

    public function testAuthenticateWithProvidersWithIncorrectInterface()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AuthenticationProviderManager([
            new \stdClass(),
        ]))->authenticate($this->createMock(TokenInterface::class));
    }

    public function testAuthenticateWhenNoProviderSupportsToken()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(false),
        ]);

        try {
            $manager->authenticate($token = $this->createMock(TokenInterface::class));
            $this->fail();
        } catch (ProviderNotFoundException $e) {
            $this->assertSame($token, $e->getToken());
        }
    }

    public function testAuthenticateWhenProviderReturnsAccountStatusException()
    {
        $secondAuthenticationProvider = $this->createMock(AuthenticationProviderInterface::class);

        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, AccountStatusException::class),
            $secondAuthenticationProvider,
        ]);

        // AccountStatusException stops authentication
        $secondAuthenticationProvider->expects($this->never())->method('supports');

        try {
            $manager->authenticate($token = $this->createMock(TokenInterface::class));
            $this->fail();
        } catch (AccountStatusException $e) {
            $this->assertSame($token, $e->getToken());
        }
    }

    public function testAuthenticateWhenProviderReturnsAuthenticationException()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, AuthenticationException::class),
        ]);

        try {
            $manager->authenticate($token = $this->createMock(TokenInterface::class));
            $this->fail();
        } catch (AuthenticationException $e) {
            $this->assertSame($token, $e->getToken());
        }
    }

    public function testAuthenticateWhenOneReturnsAuthenticationExceptionButNotAll()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, AuthenticationException::class),
            $this->getAuthenticationProvider(true, $expected = $this->createMock(TokenInterface::class)),
        ]);

        $token = $manager->authenticate($this->createMock(TokenInterface::class));
        $this->assertSame($expected, $token);
    }

    public function testAuthenticateReturnsTokenOfTheFirstMatchingProvider()
    {
        $second = $this->createMock(AuthenticationProviderInterface::class);
        $second
            ->expects($this->never())
            ->method('supports')
        ;
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $expected = $this->createMock(TokenInterface::class)),
            $second,
        ]);

        $token = $manager->authenticate($this->createMock(TokenInterface::class));
        $this->assertSame($expected, $token);
    }

    public function testEraseCredentialFlag()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar', 'key')),
        ]);

        $token = $manager->authenticate($this->createMock(TokenInterface::class));
        $this->assertEquals('', $token->getCredentials());

        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar', 'key')),
        ], false);

        $token = $manager->authenticate($this->createMock(TokenInterface::class));
        $this->assertEquals('bar', $token->getCredentials());
    }

    public function testAuthenticateDispatchesAuthenticationFailureEvent()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');
        $provider = $this->createMock(AuthenticationProviderInterface::class);
        $provider->expects($this->once())->method('supports')->willReturn(true);
        $provider->expects($this->once())->method('authenticate')->willThrowException($exception = new AuthenticationException());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(new AuthenticationFailureEvent($token, $exception)), AuthenticationEvents::AUTHENTICATION_FAILURE);

        $manager = new AuthenticationProviderManager([$provider]);
        $manager->setEventDispatcher($dispatcher);

        try {
            $manager->authenticate($token);
            $this->fail('->authenticate() should rethrow exceptions');
        } catch (AuthenticationException $e) {
            $this->assertSame($token, $exception->getToken());
        }
    }

    public function testAuthenticateDispatchesAuthenticationSuccessEvent()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');

        $provider = $this->createMock(AuthenticationProviderInterface::class);
        $provider->expects($this->once())->method('supports')->willReturn(true);
        $provider->expects($this->once())->method('authenticate')->willReturn($token);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(new AuthenticationSuccessEvent($token)), AuthenticationEvents::AUTHENTICATION_SUCCESS);

        $manager = new AuthenticationProviderManager([$provider]);
        $manager->setEventDispatcher($dispatcher);

        $this->assertSame($token, $manager->authenticate($token));
    }

    protected function getAuthenticationProvider($supports, $token = null, $exception = null)
    {
        $provider = $this->createMock(AuthenticationProviderInterface::class);
        $provider->expects($this->once())
                 ->method('supports')
                 ->willReturn($supports)
        ;

        if (null !== $token) {
            $provider->expects($this->once())
                     ->method('authenticate')
                     ->willReturn($token)
            ;
        } elseif (null !== $exception) {
            $provider->expects($this->once())
                     ->method('authenticate')
                     ->willThrowException($this->getMockBuilder($exception)->setMethods(null)->getMock())
            ;
        }

        return $provider;
    }
}
